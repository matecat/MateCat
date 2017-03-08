<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 03/03/2017
 * Time: 17:23
 */

namespace Features\Dqf\Service\Struct;


class ProjectRequestStruct extends BaseRequestStruct implements  ISessionBasedRequestStruct  {

    public $apiKey ;
    public $sessionId ;

    public $name ;
    public $sourceLanguageCode ;
    public $contentTypeId ;
    public $industryId ;
    public $processId ;
    public $qualityLevelId ;
    public $cliendId ;
    public $templateName ;
    public $tmsProjectKey ;

    public function getHeaders() {
        return $this->toArray(['apiKey', 'sessionId'] );
    }

}