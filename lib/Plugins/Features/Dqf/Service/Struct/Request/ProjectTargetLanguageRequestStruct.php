<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 10/03/2017
 * Time: 16:12
 */

namespace Features\Dqf\Service\Struct\Request;


use Features\Dqf\Service\Struct\BaseRequestStruct;

class ProjectTargetLanguageRequestStruct extends BaseRequestStruct {

    public $projectId ;
    public $fileId ;
    public $sessionId ;
    public $apiKey ;
    public $projectKey ;

    public $targetLanguageCode ;

    public function getHeaders() {
        return $this->toArray(['sessionId', 'apiKey', 'projectKey'] ) ;
    }

    public function getPathParams() {
        return ['projectId' => $this->projectId, 'fileId' => $this->fileId ] ;
    }


}