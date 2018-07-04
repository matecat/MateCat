<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 03/03/2017
 * Time: 17:23
 */

namespace Features\Dqf\Service\Struct;


class ProjectRequestStruct extends BaseRequestStruct  {

    public $apiKey ;
    public $sessionId ;

    public $projectId ;
    public $projectKey ;

    public $name ;
    public $sourceLanguageCode ;
    public $contentTypeId ;
    public $industryId ;
    public $processId ;
    public $qualityLevelId ;
    public $clientId ;
    public $templateName ;
    public $tmsProjectKey ;

    public function getPathParams() {
        return [ $this->projectId ];
    }

    public function getHeaders() {
        return array_filter( $this->toArray(['apiKey', 'sessionId', 'projectKey'] ) ) ;
    }
}