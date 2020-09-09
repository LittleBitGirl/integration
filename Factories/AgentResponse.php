<?php


namespace App\Models\Integration\Factories;


use Illuminate\Http\JsonResponse;
use PHPUnit\Util\Json;

class AgentResponse
{

    protected $responseBody;
    protected $responseStatus;


    /**
     * @return mixed
     */
    public function getResponseBody()
    {
        return $this->responseBody;
    }

    /**
     * @param mixed $responseBody
     */
    public function setResponseBody($responseBody): void
    {
        $this->responseBody = $responseBody;
    }

    /**
     * @return mixed
     */
    public function getResponseStatus(): int
    {
        return $this->responseStatus;
    }

    /**
     * @param mixed $responseStatus
     */
    public function setResponseStatus($responseStatus): void
    {
        $this->responseStatus = $responseStatus;
    }

    /**
     * @return JsonResponse
     */
    public function returnJsonResponse(): JsonResponse
    {
        return response()->json(
            $this->getResponseBody(),
            $this->getResponseStatus()
        );
    }
}