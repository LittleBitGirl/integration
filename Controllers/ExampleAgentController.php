<?php


namespace App\Http\Controllers;


use App\Http\Controllers\Controller;
use App\Models\Integration\Realizations\ExampleAgent;
use \Illuminate\Http\JsonResponse;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Psr\Http\Message\StreamInterface;

class ExampleAgentController extends Controller
{


    /** Request for calculation of a sum
     * @param Request $request
     * @return JsonResponse
     *
     */
    public function checkSum(Request $request)
    {
        $exampleAgent = new ExampleAgent();
        $exampleAgent->getAgentFactory()->createCalculateSumRequest($request);
        $exampleAgent->selectSuitableExchangeBehavior($request->serviceId);
        return $exampleAgent->getExchangeBehavior()->calculatePaymentAmount($exampleAgent);
    }


    /** Request for handshake before transaction
     * @param Request $request
     * @return JsonResponse
     *
     * @throws GuzzleException
     */
    public function checkAccount(Request $request)
    {
        $exampleAgent = new ExampleAgent();
        $exampleAgent->getAgentFactory()->createCheckAccountRequest($request);
        $agentResponse = $exampleAgent->sendRequest();
        return $exampleAgent->getAgentFactory()->createCheckAccountResponse($agentResponse);
    }


	/** Request to proceed the transaction
	 * @param Request $request
	 * @return JsonResponse|StreamInterface
	 *
	 * @throws GuzzleException
	 * @throws Exception
	 */
    public function conductPayment(Request $request)
    {
        $exampleAgent = new ExampleAgent();
        $exampleAgent->selectSuitableProcessBehavior($request->serviceId);
        try {
            return $exampleAgent->getProcessBehavior()->conductPayment($request);
        } catch (Exception $e) {
            DB::rollback();
            Log::error($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 200);
        }
    }

    /** Request for transaction status
     * @param Request $request
     * @return StreamInterface
     *
     * @throws GuzzleException
     */
    public function getPaymentStatus(Request $request)
    {
        $exampleAgent = new ExampleAgent();
        $exampleAgent->getAgentFactory()->createCheckStatusRequest($request);
        $exampleResponse = $exampleAgent->sendRequest();
        return $exampleAgent->getAgentFactory()->createCheckStatusResponse($exampleResponse);
    }
}