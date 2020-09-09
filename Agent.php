<?php


namespace App\Models\Integration;

use App\BalanceHistory;
use App\Customer;
use App\Log;
use App\Models\Integration\Behaviors\CheckAccountProcessBehavior;
use App\Models\Integration\Behaviors\ExchangeBehavior;
use App\Models\Integration\Behaviors\ProcessBehavior;
use App\Models\Integration\Behaviors\StraightProcessBehavior;
use App\Models\Integration\Factories\AgentFactory;
use App\Payment;
use App\Service;
use App\SourceTable;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use phpDocumentor\Reflection\Types\Boolean;
use phpDocumentor\Reflection\Types\Integer;
use Symfony\Component\Process\Process;

abstract class Agent
{

    protected ExchangeBehavior $exchangeBehavior;
    protected ProcessBehavior $processBehavior;
    protected $requestHeader;
    protected $baseUri;
    protected $defaultRequestParams;
    protected $statusCheckRequestParams;
    protected $requestBody;
    protected $requestMethod;
    protected $agentFactory;
    protected $agentSourceTable;
    protected $agentServiceModel;

    const DONE = 2;
    const FAIL = 1;
    const PROCESS = 0;

    /**
     * @param $serviceId
     * @return void
     */
    abstract public function selectSuitableExchangeBehavior($serviceId);

    /**
     * @param JsonResponse $conductPaymentResponse
     * @return Int
     */
    abstract public function checkConductPaymentResponseStatus(JsonResponse $conductPaymentResponse): Int;


    /**
     * @param array $checkStatusResponse
     * @param Payment $payment
     */
    abstract public function checkStatusResponseAndHandle(array $checkStatusResponse, Payment $payment): void;

    /**
     * @param $toChangeAmount
     * @return void
     */
    abstract public function setRequestBodyAmountParameter($toChangeAmount);

    /**
     * @return double|int
     */
    abstract public function getRequestBodyAmountParameter();

    /**
     * @param $serviceId
     * @return string
     */
    public function getServiceGateway($serviceId): string
    {
        return app($this->agentServiceModel)::where('service_id', '=', intval($serviceId))->first()->gateway;
    }

    /**
     * @param $gateway
     * @return string
     */
    public function getGatewayService($gateway): string
    {
       return app($this->agentServiceModel)::where('gateway', '=', $gateway)->first()->service_id;
    }

    /**
     * @param $serviceId
     */
    public function selectSuitableProcessBehavior($serviceId)
    {
        $service = Service::find($serviceId);
        if ($service->provider->is_checkable) {
            $this->setProcessBehavior(new CheckAccountProcessBehavior($this));
        } else {
            $this->setProcessBehavior(new StraightProcessBehavior($this));
        }
    }


    /**
     * @return mixed
     * @throws GuzzleException
     */
    public function sendRequest()
    {

        $guzzleClient = new Client($this->baseUri);

        $guzzleResponse = $guzzleClient->request($this->requestMethod, '', [
            'headers' => $this->requestHeader,
            'form_params' => array_merge($this->defaultRequestParams, $this->requestBody)
        ])->getBody();

        return json_decode($guzzleResponse->getContents(), true);
    }

    /**
     * @param $request
     * @return Payment
     */
    public function createPayment($request)
    {
        $payment = new Payment;
        $payment->service_id = $request->serviceID;
        $payment->customer_id = $request->customerID;
        $payment->amount = $request->amount;
        $payment->user_input = json_encode($request->except('unique'));
        $payment->status = Payment::STATUS_PROCESS;
        $payment->save();
        return $payment;
    }

    /**
     * @param $paymentId
     * @param $status
     */
    public function updatePaymentStatus($paymentId, $status)
    {
        $payment = Payment::find($paymentId)->first();
        $payment->status = $status;
        $payment->save();
    }

    /**
     * @param Payment $payment
     * @throws Exception
     */
    public function setBalanceInBlockOrFail(Payment $payment)
    {
        $customer = Customer::find($payment->customer_id)->first();
        $balanceInBlockAfter = $customer->balance - $payment->amount;

        if ($balanceInBlockAfter < 0) {
            $this->updatePaymentStatus($payment->id, Payment::STATUS_FAIL);
            throw new Exception('Сумма в блоке составляет меньше 0 (' . $balanceInBlockAfter . '), ID пользователя ' . $customer->id);
        }

        try {
            DB::beginTransaction();
            $customer->decrement('balance', $payment->amount);
            $customer->increment('balance_in_block', $payment->amount);
            $customer->save();
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            Log::error($e->getMessage() . ' FILE: ' . $e->getFile() . ' ON LINE: ' . $e->getLine());
        }
    }

    /**
     * @param Payment $payment
     * @throws Exception
     */
    public function resetBalanceInBlock(Payment $payment)
    {
        $customer = Customer::find($payment->customer_id);
        DB::beginTransaction();
        $customer->increment('balance', $payment->amount);
        $customer->decrement('balance_in_block', $payment->amount);
        $customer->save();
        DB::commit();
    }

    /**
     * @param $payment
     * @return BalanceHistory
     */
    public function recordBalanceHistory($payment)
    {
        $customer = Customer::find($payment->customer_id);
        $balanceHistory = new BalanceHistory;
        $balanceHistory->status = $balanceHistory::BALANCE_HISTORY_STATUS_NEW;
        $balanceHistory->customer_id = $payment->customer_id;
        $balanceHistory->amount = $payment->amount;
        $balanceHistory->balance_before = $customer->balance;
        $balanceHistory->balance_after = $customer->balance - $payment->amount;
        $balanceHistory->blockBefore = $customer->balance_in_block + $payment->amount;
        $balanceHistory->blockAfter = $customer->balance_in_block;
        $balanceHistory->type = $balanceHistory::BALANCE_HISTORY_TYPE_OUT;
        $balanceHistory->description = 'Оплата услуги ' . Service::find($payment->service_id)->title;
        $balanceHistory->source_table_id = SourceTable::where('table', $this->agentSourceTable)->first()->id;
        $balanceHistory->source_id = 0;
        $balanceHistory->save();
        return $balanceHistory;
    }

    /**
     * @param BalanceHistory $balanceHistory
     * @param $status
     * @return BalanceHistory
     */
    public function updateBalanceHistoryStatus(BalanceHistory $balanceHistory, $status)
    {
        $balanceHistory->status = $status;
        $balanceHistory->save();
        return $balanceHistory;
    }

    /**
     * @return ExchangeBehavior
     * */
    public function getExchangeBehavior()
    {
        return $this->exchangeBehavior;
    }

    /**
     * @param ExchangeBehavior $exchangeBehavior
     * @return void
     */
    public function setExchangeBehavior(ExchangeBehavior $exchangeBehavior)
    {
        $this->exchangeBehavior = $exchangeBehavior;
    }

    /**
     * @return mixed
     */
    public function getAgentServiceModel()
    {
        return $this->agentServiceModel;
    }

    /**
     * @param $agentServiceModel
     */
    public function setAgentServiceModel($agentServiceModel): void
    {
        $this->agentServiceModel = $agentServiceModel;
    }


    /**
     * @return mixed
     */
    public function getProcessBehavior()
    {
        return $this->processBehavior;
    }

    /**
     * @param mixed $processBehavior
     */
    public function setProcessBehavior($processBehavior): void
    {
        $this->processBehavior = $processBehavior;
    }

    /**
     * @param string $baseUri
     * @return void
     */
    public function setBaseUri(string $baseUri)
    {
        $this->baseUri['base_uri'] = $baseUri;
    }

    /**
     * @return string $baseUri
     */
    public function getBaseUri()
    {
        return $this->baseUri;
    }

    /**
     * @param string $requestMethod
     * @return void
     */
    public function setRequestMethod(string $requestMethod)
    {
        $this->requestMethod = $requestMethod;
    }

    /**
     * @return mixed
     */
    public function getRequestMethod()
    {
        return $this->requestMethod;
    }

    /**
     * @param array $requestHeader
     */
    public function setRequestHeader(array $requestHeader)
    {
        $this->requestHeader = $requestHeader;
    }

    /**
     * @return mixed
     */
    public function getRequestHeader()
    {
        return $this->requestHeader;
    }

    /**
     * @param mixed $requestBody
     */
    public function setRequestBody($requestBody): void
    {
        $this->requestBody = $requestBody;
    }

    /**
     * @return mixed
     */
    public function getRequestBody()
    {
        return $this->requestBody;
    }

    /**
     * @return mixed
     */
    public function getDefaultRequestParams()
    {
        return $this->defaultRequestParams;
    }

    /**
     * @param mixed $defaultRequestParams
     */
    public function setDefaultRequestParams($defaultRequestParams): void
    {
        $this->defaultRequestParams = $defaultRequestParams;
    }

    /**
     * @return mixed
     */
    public function getAgentFactory()
    {
        return $this->agentFactory;
    }

    /**
     * @param AgentFactory $agentFactory
     */
    public function setAgentFactory(AgentFactory $agentFactory)
    {
        $this->agentFactory = $agentFactory;
    }

    /**
     * @return mixed
     */
    public function getAgentSourceTable()
    {
        return $this->agentSourceTable;
    }

    /**
     * @param mixed $agentSourceTable
     */
    public function setAgentSourceTable($agentSourceTable): void
    {
        $this->agentSourceTable = $agentSourceTable;
    }

    /**
     * @return array
     */
    public function getStatusCheckRequestParams(): array
    {
        return $this->statusCheckRequestParams;
    }

    /**
     * @param array $statusCheckRequestParams
     */
    public function setStatusCheckRequestParams(array $statusCheckRequestParams): void
    {
        $this->statusCheckRequestParams = $statusCheckRequestParams;
    }


}