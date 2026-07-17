<?php

namespace Utils\Engines\Results\MyMemory;

use TypeError;
use Utils\Engines\Results\TMSAbstractResponse;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 03/03/15
 * Time: 12.33
 */
class ExportResponse extends TMSAbstractResponse
{

    public mixed $id = '';
    public string $resourceLink = '';
    public mixed $estimatedTime = '';

    /**
     * @param array<string, mixed> $response
     *
     * @throws TypeError
     */
    public function __construct(array $response)
    {
        $this->responseStatus = (int)($response['responseStatus'] ?? 200);
        $this->responseData = $response['responseData'] ?? '';
        $this->id = $response['responseData']['id'] ?? '';
        $this->resourceLink = $response['resourceLink'] ?? '';
        $this->responseDetails = $response['status'] ?? '';
        $this->estimatedTime = $response['estimated_time'] ?? '';
    }

} 