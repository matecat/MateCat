<?php

namespace Utils\Engines\Results\MyMemory;

use Utils\Engines\Results\TMSAbstractResponse;

/**
 * This class is @deprecated as deprecated is the MyMemory payload for SET CONTRIBUTION
 * This class Will be Merged with SetContributionResponse
 * @deprecated
 *
 */
class UpdateContributionResponse extends TMSAbstractResponse
{

    public function __construct($response)
    {
        /*
         * deprecated response:
         *
         * {"responseData":"OK","responseStatus":200,"number_of_results":1,"segment_ids":["397a8484-39de-9804-5c29-2d7de7cabe07"]}}
         */
        $this->responseDetails = [
            'number_of_results' => $response['number_of_results'] ?? 0,
            'segment_ids' => $response['segment_ids'] ?? []
        ];

        $this->responseData = $response['responseData'] ?? '';
        $this->responseStatus = (int)($response['responseStatus'] ?? 200);
    }

} 