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
class SetContributionResponse extends TMSAbstractResponse
{

    /**
     * @param array<string, mixed> $response
     *
     * @throws TypeError
     */
    public function __construct(array $response)
    {
        $this->responseData = $response['responseData'] ?? '';
        $this->responseDetails = $response['responseDetails'] ?? '';
        $this->responseStatus = (int)($response['responseStatus'] ?? 200);
    }

} 