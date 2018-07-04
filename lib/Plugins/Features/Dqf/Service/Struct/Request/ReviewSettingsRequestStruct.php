<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 30/05/2017
 * Time: 17:12
 */

namespace Features\Dqf\Service\Struct\Request ;

use Features\Dqf\Service\Struct\BaseRequestStruct;

class ReviewSettingsRequestStruct extends BaseRequestStruct {

    public $projectId ;
    public $sessionId ;
    public $apiKey ;
    public $projectKey ;

    public $templateName ;
    public $reviewType ;

    public $severityWeights ;

    public $errorCategoryIds = array() ;

    public $passFailThreshold ;
    public $sampling ;

    public function getHeaders() {
        return $this->toArray(['sessionId', 'apiKey', 'projectKey'] ) ;
    }

    public function getPathParams() {
        return ['projectId' => $this->projectId ] ;
    }

    public function addErrorCategory( $category ) {
        array_push( $this->errorCategoryIds, $category ) ;
    }

}