<?php


namespace App\Models\Integration\Behaviors;


use App\Models\Integration\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Request;

interface ExchangeBehavior
{
    /**
     * @param Agent $agent
     * @return JsonResponse
     */
    public function calculatePaymentAmount(Agent $agent): JsonResponse;

}