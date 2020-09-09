<?php


namespace App\Models\Integration\Behaviors;


use App\Models\Integration\Agent;
use App\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface ProcessBehavior
{

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function conductPayment(Request $request): JsonResponse;

    /**
     * @param Payment $payment
     * @return mixed
     */
    public function getPrimaryResponse(Payment $payment): JsonResponse;
}