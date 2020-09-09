<?php


namespace App\Models\Integration\Factories;


use App\HermesService;
use App\Models\Integration\Agent;
use App\Payment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExampleAgentFactory implements AgentFactory
{

    public Agent $agent;

    /**
     * HermesAgentFactory constructor.
     * @param $agent
     */
    public function __construct($agent)
    {
        $this->agent = $agent;
    }

    /**
     * @param Request $request
     * @return mixed|void
     */
    public function createCalculateSumRequest($request)
    {
        $this->agent->setRequestBody([
            'RequestType' => 'CalculatedSum',
            'Service' => $this->agent->getServiceGateway($request->serviceID),
            'Amount' => $request->amount,
            'account' => $request->account
        ]);
    }

    /**
     * @param Payment $payment
     * @return mixed|void
     */
    public function createCheckAccountRequest(Payment $payment)
    {
        $this->agent->setRequestBody([
            'RequestType' => 'AccountCheck',
            'Service' => $this->agent->getServiceGateway($payment->service_id),
            'Amount' => $payment->amount,
            'TransactionID' => $payment->id,
            'RequestDate' => Carbon::now()->format('Y-m-d H:m:s'),
            'account' => json_decode($payment->user_input, true)['account'],
        ]);
    }

    /**
     * @param Payment $payment
     * @return mixed|void
     */
    public function createConductPaymentRequest(Payment $payment)
    {
        $this->agent->setRequestBody([
            'RequestType' => 'Payment',
            'Service' => $this->agent->getServiceGateway($payment->service_id),
            'Amount' => $payment->amount,
            'TransactionID' => $payment->id,
            'RequestDate' => Carbon::now()->format('Y-m-d H:m:s'),
            'account' => json_decode($payment->user_input, true)['account']
        ]);
    }

    /**
     * @param $payment
     * @return mixed|void
     */
    public function createCheckStatusRequest($payment)
    {
        $this->agent->setRequestBody([
            'RequestType' => 'Status',
            'TransactionID' => $payment->id
        ]);
    }

    /**
     * @param $response
     * @return JsonResponse
     */
    public function createCalculateSumResponse($response): JsonResponse
    {
        $calculateSumResponse = new AgentResponse();
        $calculateSumResponse->setResponseBody([ //ВАЖНО ИСПОЛЬЗОВАТЬ ОБЩЕПРИНЯТЫЕ КОНСТАНТЫ
            self::SERVICE_ID_PARAMETER => $this->agent->getGatewayService($response['Service']),
            self::CURRENCY_FROM_PARAMETER => $response['ExpectedCurrency'],
            self::CURRENCY_TO_PARAMETER => $response['Currency'],
            self::AMOUNT_PARAMETER => $response['Amount'],
            self::EXCHANGE_RATE_PARAMETER => $response['ExchangeRate'],
            self::CONVERTED_AMOUNT_PARAMETER => $response['ExpectedAmount']
        ]);
        $calculateSumResponse->setResponseStatus(200);

        return $calculateSumResponse->returnJsonResponse();
    }

    /**
     * @param $response
     * @return JsonResponse
     */
    public function createCheckAccountResponse($response): JsonResponse
    {
        $checkAccountResponse = new AgentResponse();
        if($response['ResponseStatus'] == 10){
            $checkAccountResponse->setResponseBody(true);
            $checkAccountResponse->setResponseStatus(200);
        } else{
            $checkAccountResponse->setResponseBody([
                'message' => $response['Message'],
                'data' => $response
            ]);
            $checkAccountResponse->setResponseStatus(400);
        }
        return $checkAccountResponse->returnJsonResponse();
    }


    /**
     * @param $response
     * @return JsonResponse
     */
    public function createConductPaymentResponse($response): JsonResponse
    {
        $conductPaymentResponse = new AgentResponse();
        $conductPaymentResponse->setResponseBody([ //ВАЖНО ИСПОЛЬЗОВАТЬ ОБЩЕПРИНЯТЫЕ КОНСТАНТЫ, если нет нужной - добавьте в AgentFactory
            self::REQUEST_ID_PARAMETER => $response['RequestID'],
            self::TRANSACTION_ID_PARAMETER => $response['TransactinID'],
            self::TRANSACTION_CONTENT_PARAMETER => $response['TransactionContent'],
            self::RESPONSE_STATUS_PARAMETER => $response['ResponseStatus'],
            self::MESSAGE_PARAMETER => $response['Message'],
        ]);
        $conductPaymentResponse->setResponseStatus(200);
        return $conductPaymentResponse->returnJsonResponse();
    }

    /**
     * @param $response
     * @return AgentResponse
     */
    public function createCheckStatusResponse($response): JsonResponse
    {
        $checkStatusResponse = new AgentResponse();
        $checkStatusResponse->setResponseBody([
            self::TRANSACTION_ID_PARAMETER => $response['TransactinID'],
            self::RESPONSE_STATUS_PARAMETER => $response['ResponseStatus'],
            self::MESSAGE_PARAMETER => $response['Message'],
            self::TRANSACTION_CONTENT_PARAMETER => $response['TransactionContent']
        ]);
        $checkStatusResponse->setResponseStatus(200);
        return $checkStatusResponse->returnJsonResponse();
    }
}