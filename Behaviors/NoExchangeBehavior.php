<?php


namespace App\Models\Integration\Behaviors;


use App\Models\Integration\Agent;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Request;

class NoExchangeBehavior implements ExchangeBehavior
{
    /**
     * @param Agent $agent
     * @return JsonResponse
     */
    public function calculatePaymentAmount(Agent $agent): JsonResponse
    {
        return response()->json([
            'message' => 'no need to convert',
            'data' => $agent->getRequestBody()
        ], 200);
    }

}