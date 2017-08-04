<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 10/07/2017
 * Time: 17:15
 */

namespace Features\Dqf\Service\Struct\Request;


use Features\Dqf\Service\Struct\BaseRequestStruct;

class ChildProjectTranslationRequestStruct extends BaseRequestStruct {

    public $projectId ;
    public $fileId ;
    public $targetLangCode ;
    public $sessionId ;
    public $apiKey ;
    public $projectKey ;

    protected $_segmentsForBody ;

    public function getHeaders() {
        return $this->toArray(['sessionId', 'apiKey', 'projectKey']);
    }

    /**
     * @return array
     */
    public function getPathParams() {
        return [
                'projectId'      => $this->projectId,
                'fileId'         => $this->fileId,
                'targetLangCode' => $this->targetLangCode
        ];
    }

    /**
     * @return array
     */
    public function getBody() {
        return  [
                'segmentPairs' => $this->_segmentsForBody
        ];
    }

    public function setSegments( $segments ) {
        $this->_segmentsForBody = $segments ;
    }
}