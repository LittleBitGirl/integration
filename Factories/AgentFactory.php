<?php


namespace App\Models\Integration\Factories;


use App\Payment;
use App\Models\Integration\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface AgentFactory
{
    const RESPONSE_TYPE_PARAMETER = 'ResponseType';
    const AMOUNT_PARAMETER = 'Amount';
    const CURRENCY_FROM_PARAMETER = 'CurrencyFrom';
    const EXCHANGE_RATE_PARAMETER = 'ExchangeRate';
    const CONVERTED_AMOUNT_PARAMETER = 'ConvertedAmount';
    const CURRENCY_TO_PARAMETER = 'CurrencyTo';
    const SERVICE_ID_PARAMETER = 'ServiceId';
    const REQUEST_ID_PARAMETER = 'RequestId';
    const TRANSACTION_ID_PARAMETER = 'TransactionId';
    const RESPONSE_STATUS_PARAMETER = 'ResponseStatus';
    const MESSAGE_PARAMETER = 'Message';
    const TRANSACTION_CONTENT_PARAMETER = 'TransactionContent';

    /**
     * @param Request $request
     * @return mixed
     */
    public function createCalculateSumRequest(Request $request);

    /**
     * @param Payment $payment
     * @return mixed
     */
    public function createCheckAccountRequest(Payment $payment);

    /**
     * @param Payment $payment
     * @return mixed
     */
    public function createConductPaymentRequest(Payment $payment);

    /**
     * @param Payment $payment
     * @return mixed
     */
    public function createCheckStatusRequest(Payment $payment);


    /**
     * @param $response
     * @return JsonResponse
     */
    public function createCalculateSumResponse($response) : JsonResponse;

    /**
     * @param $response
     * @return JsonResponse
     */
    public function createCheckAccountResponse($response) : JsonResponse;

    /**
     * @param $response
     * @return JsonResponse
     */
    public function createConductPaymentResponse($response) : JsonResponse;

    /**
     * @param $response
     * @return AgentResponse
     */
    public function createCheckStatusResponse($response) : JsonResponse;

}