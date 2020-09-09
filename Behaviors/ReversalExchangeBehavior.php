<?php


namespace App\Models\Integration\Behaviors;


use App\Models\Integration\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Request;

class ReversalExchangeBehavior implements ExchangeBehavior
{

    /**
     * @param Agent $agent
     * @return JsonResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function calculatePaymentAmount(Agent $agent): JsonResponse
    {
        $preconvertedSum = $this->getPreConvertedSum($agent);
        $agent->setRequestBodyAmountParameter($preconvertedSum);
        $calculateSumRequest = $agent->getAgentFactory()->createCalculateSumRequest();
        $agent->setRequestBody($calculateSumRequest->getRequestBody);
        $response = $agent->sendRequest();
        return $agent->getAgentFactory()->createCalculateSumResponse($response);
    }

    /**
     * @param Agent $agent
     * @return float|int
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getPreConvertedSum(Agent $agent)
    {
        $amount = $agent->getRequestBodyAmountParameter();
        $agent->setRequestBodyAmountParameter(1);
        $agent->getAgentFactory()->createCalculateSumRequest();
        $response = json_decode($agent->sendRequest(), TRUE);
        $exchangeRate = $response['ExchangeRate'];
        return $amount * $exchangeRate;
    }
}