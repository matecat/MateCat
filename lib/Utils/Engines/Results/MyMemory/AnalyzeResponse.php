<?php

namespace Utils\Engines\Results\MyMemory;

use TypeError;
use Utils\Engines\Results\TMSAbstractResponse;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 04/03/15
 * Time: 11.50
 */
class AnalyzeResponse extends TMSAbstractResponse
{

    /**
     * @param array<string, mixed> $response
     *
     * @throws TypeError
     */
    public function __construct(array $response)
    {
        $this->responseStatus = (int)($response['responseStatus'] ?? 200);
        $this->responseDetails = $response['responseData'] ?? '';
        $this->responseData = $response['data'] ?? '';
    }

} 