<?php

namespace Utils\Engines\Results\MyMemory;

use Utils\Engines\Results\TMSAbstractResponse;
use Utils\Logger\LoggerFactory;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 03/03/15
 * Time: 15.33
 */
class FileImportAndStatusResponse extends TMSAbstractResponse
{

    /*
    {
        "messageType": "tms-import",
        "responseData": {
            "uuid": "eab692c7-0872-aa4f-5abf-9cd333df48f0",
            "id": null,
            "creation_date": "2023-04-28 15:32:24",
            "totals": null,
            "completed": 0,
            "skipped": 0,
            "status": 0
        },
        "responseStatus": 202
    }
    */

    public $id;

    public function __construct($response)
    {
        $this->responseData    = $response[ 'responseData' ] ?? '';
        $this->responseStatus  = (int)($response[ 'responseStatus' ] ?? 200);
        $this->responseDetails = $response[ 'responseDetails' ] ?? '';

        if ($this->responseStatus == 200 || $this->responseStatus == 202) {
            $this->id = empty($this->responseData[ 'uuid' ]) ? $this->responseData[ 'UUID' ] : $this->responseData[ 'uuid' ];
        } else {
            LoggerFactory::doJsonLog($response);
        }
    }

} 