<?php


namespace App\Http\Controllers;


use Controller;
use App\Service;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Http\Message\StreamInterface;

class AgentController extends Controller
{
    /** Request for calculation of a sum
     * @param Request $request
     * @return JsonResponse
     *
     */
    public function checkSum(Request $request)
    {
        return app()
            ->make($this->chooseRightAgentController($request->serviceId))
            ->checkSum($request);
    }


    /** Request to proceed the transaction
     * @param Request $request
     * @return JsonResponse|StreamInterface
     *
     * @throws GuzzleException
     */
    public function conductPayment(Request $request)
    {
        return app()
            ->make($this->chooseRightAgentController($request->serviceId))
            ->conductPayment($request);
    }

    /** Request for transaction status
     * @param Request $request
     * @return StreamInterface
     *
     * @throws GuzzleException
     */
    public function getPaymentStatus(Request $request)
    {
        return app()
            ->make($this->chooseRightAgentController($request->serviceId))
            ->getPaymentStatus($request);
    }

    public function chooseRightAgentController($serviceId)
    {
        $provider_id = Service::where('id', '=', intval($serviceId))->first()->provider_id;
        return config('services.agents.' . $provider_id)['controller'];
    }
}