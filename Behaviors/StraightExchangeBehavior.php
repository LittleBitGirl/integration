<?php


namespace App\Models\Integration\Behaviors;


use App\Models\Integration\Agent;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Request;

class StraightExchangeBehavior implements ExchangeBehavior
{
    /**
     * @param Agent $agent
     * @return JsonResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function calculatePaymentAmount(Agent $agent): JsonResponse
    {
        $agentResponse = $agent->sendRequest();
        return $agent->getAgentFactory()->createCalculateSumResponse($agentResponse);
    }

}