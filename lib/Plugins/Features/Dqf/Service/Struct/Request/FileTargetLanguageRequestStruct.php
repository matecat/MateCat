<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 10/03/2017
 * Time: 12:30
 */

namespace Features\Dqf\Service\Struct\Request;


use Features\Dqf\Service\Struct\BaseRequestStruct;
use Features\Dqf\Service\Struct\ISessionBasedRequestStruct;

class FileTargetLanguageRequestStruct extends BaseRequestStruct implements  ISessionBasedRequestStruct  {

    public $sessionId ;
    public $apiKey ;
    public $projectKey ;

    public $fileId;
    public $projectId  ;

    public $targetLanguageCode ;

    public function getHeaders() {
        return $this->toArray(['apiKey', 'sessionId', 'projectKey']);
    }

    public function getPathParams() {
        return ['projectId' => $this->projectId, 'fileId' => $this->fileId ] ;
    }
}