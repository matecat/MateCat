<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 10/03/2017
 * Time: 14:53
 */

namespace Features\Dqf\Service\Struct\Request;

use Features\Dqf\Service\Struct\BaseRequestStruct;

class ChildProjectRequestStruct extends BaseRequestStruct {

    public $sessionId ;
    public $apiKey ;
    public $parentKey ;
    public $projectId ;
    public $projectKey ;
    public $name ;
    public $type ;
    public $assignee ;
    public $reviewSettingId ;
    public $clientId ;

    public function getPathParams() {
        return array_filter( [ $this->projectId  ] );
    }

    public function getParams() {
        return $this->toArray(['parentKey', 'name', 'type', 'assignee', 'clientId', 'reviewSettingId']) ;
    }

    public function getHeaders() {
        return array_filter( $this->toArray(['sessionId', 'apiKey', 'projectKey']) ) ;
    }

}
