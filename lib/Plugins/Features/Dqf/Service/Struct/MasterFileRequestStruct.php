<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 06/03/2017
 * Time: 15:14
 */

namespace Features\Dqf\Service\Struct;


class MasterFileRequestStruct extends BaseRequestStruct implements ISessionBasedRequestStruct  {

    // public $projectId ;

    public $sessionId ;
    public $apiKey ;
    public $projectKey;

    public $name ;
    public $numberOfSegments ;
    public $clientId ;

    public function getHeaders() {
        return $this->toArray(['sessionId', 'apiKey', 'projectKey']);
    }

}