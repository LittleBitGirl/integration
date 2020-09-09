<?php


namespace App\Models\Integration\Behaviors;


use App\BalanceHistory;
use App\Models\Integration\Agent;
use App\Models\Integration\Factories\AgentFactory;
use App\Payment;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckAccountProcessBehavior implements ProcessBehavior
{

    public Agent $agent;

    /**
     * CheckAccountProcessBehavior constructor.
     * @param Agent $agent
     */
    public function __construct(Agent $agent)
    {
        $this->agent = $agent;
    }

    /**
     * @param Payment $payment
     * @return JsonResponse
     * @throws GuzzleException
     */
    public function checkAccountAvailability(Payment $payment): JsonResponse
    {
        $this->agent->getAgentFactory()->createCheckAccountRequest($payment);
        $agentResponse = $this->agent->sendRequest();
        return $this->agent->getAgentFactory()->createCheckAccountResponse($agentResponse);
    }

	/**
	 * @param Request $request
	 * @return JsonResponse
	 * @throws GuzzleException
	 * @throws \Exception
	 */
    public function conductPayment(Request $request): JsonResponse
    {
        $payment = $this->agent->createPayment($request);
        $checkAccountResult = $this->checkAccountAvailability($payment);
        if ($checkAccountResult) {
            $this->agent->setBalanceInBlockOrFail($payment);
            $balanceHistory = $this->agent->recordBalanceHistory($payment);
            $conductPaymentResponse = $this->getPrimaryResponse($payment);
            $paymentStatus = $this->agent->checkConductPaymentResponseStatus($conductPaymentResponse);
            if ($paymentStatus == Agent::DONE) {
                $this->agent->updatePaymentStatus($payment, Payment::STATUS_SUCCESS);
                $this->agent->updateBalanceHistoryStatus($balanceHistory, BalanceHistory::BALANCE_HISTORY_STATUS_SUCCESS);
            } else if ($paymentStatus == Agent::FAIL) {
                $this->agent->resetBalanceInBlock($payment);
                $this->agent->updateBalanceHistoryStatus($balanceHistory, BalanceHistory::BALANCE_HISTORY_STATUS_FAILED);
                $this->agent->updatePaymentStatus($payment, Payment::STATUS_FAIL);
            } else {
                $this->agent->updatePaymentStatus($payment, Payment::STATUS_PROCESS_2);
                $this->agent->updateBalanceHistoryStatus($balanceHistory, BalanceHistory::BALANCE_HISTORY_STATUS_IN_PROCESS);
            }

            return $conductPaymentResponse;
        } else {
            return response()->json([
                'message' => 'Account not found',
                'data' => $checkAccountResult
            ], 400);
        }
    }

    /**
     * @param Payment $payment
     * @return JsonResponse
     * @throws GuzzleException
     */
    public function getPrimaryResponse(Payment $payment): JsonResponse
    {
        $this->agent->getAgentFactory()->createConductPaymentRequest($payment);
        $agentResponse = $this->agent->sendRequest();
        return $this->agent->getAgentFactory()->createConductPaymentResponse($agentResponse);
    }

}