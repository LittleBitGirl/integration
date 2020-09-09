<?php


namespace App\Models\Integration\Realizations;


use App\Payment;
use App\Models\Integration\Agent;
use App\Models\Integration\Behaviors\CheckAccountProcessBehavior;
use App\Models\Integration\Behaviors\NoExchangeBehavior;
use App\Models\Integration\Behaviors\StraightExchangeBehavior;
use App\Models\Integration\Behaviors\ExchangeBehavior;
use App\Models\Integration\Behaviors\ReversalExchangeBehavior;
use App\Models\Integration\Factories\AgentFactory;
use App\Service;
use Illuminate\Http\JsonResponse;

class ExampleAgent extends Agent
{

    const STATUS_SUCCESS = 10;

    /**
     * HermesAgent constructor.
     */
    public function __construct()
    {
        $this->baseUri = [
            'base_uri' => config('constants.EXAMPLE_AGENT_URI')
        ];
        $this->requestHeader = [
            'Content-Type' => "application/x-www-form-urlencoded"
        ];
        $this->defaultRequestParams = [
            'AgentId' => env('EXAMPLE_AGENT_ID', 1),
            'AgentPassword' => env('EXAMPLE_PASSWORD', 'AgentTest'),
            'Currency' => 'KZT'
        ];
        $this->requestMethod = 'POST';

        $this->setProcessBehavior(new CheckAccountProcessBehavior($this));
        $this->setAgentSourceTable('ExampleAgentPayments');
        $this->setAgentServiceModel(ExampleService::class);
        $this->setAgentFactory(new ExampleAgentFactory($this));
    }

    /**
     * @param $serviceId
     */
    public function selectSuitableExchangeBehavior($serviceId)
    {
        $service = Service::find($serviceId);
        if ($service->is_convertable && $service->is_reverse_convertable) {
            $this->setExchangeBehavior(new ReversalExchangeBehavior());
        } elseif ($service->is_convertable && !$service->is_reverse_convertable) {
            $this->setExchangeBehavior(new StraightExchangeBehavior());
        } else {
            $this->setExchangeBehavior(new NoExchangeBehavior());
        }
    }


    /**
     * @param $toChangeAmount
     */
    public function setRequestBodyAmountParameter($toChangeAmount)
    {
        $requestBody = $this->requestBody;
        $requestBody['Amount'] = $toChangeAmount;
        $this->requestBody = $requestBody;
    }

    /**
     * @return float|int
     */
    public function getRequestBodyAmountParameter()
    {
        $requestBody = $this->requestBody;
        return $requestBody['Amount'];
    }

    /**
     * @param JsonResponse $conductPaymentResponse
     * @return int
     */
    public function checkConductPaymentResponseStatus(JsonResponse $conductPaymentResponse): Int
    {
        $response = $conductPaymentResponse->getData(true);
        $status = ExampleAgentStatus::where('status_code', $response[AgentFactory::RESPONSE_STATUS_PARAMETER])->first();
        $statusCode = 0;
        if ($status->is_error && $status->is_final) {
            $statusCode = self::FAIL;
        } else if (!$status['is_final']) {
            $statusCode = self::PROCESS;
        } else if ($status['is_final'] && !$status['is_error']) {
            $statusCode = self::DONE;
        }
        return $statusCode;
    }

    public function checkStatusResponseAndHandle(array $checkStatusResponse, Payment $payment): void
    {
        $status = ExampleAgentStatus::where('status_code', $checkStatusResponse[AgentFactory::RESPONSE_STATUS_PARAMETER])->first();
        if ($status->is_error && $status->is_final) {
            $this->resetBalanceInBlock($payment);
            $this->updatePaymentStatus($payment, Payment::STATUS_FAIL);
        } else if ($status['is_final'] && !$status['is_error']) {
            $this->updatePaymentStatus($payment, Payment::STATUS_SUCCESS);
        }
    }
}