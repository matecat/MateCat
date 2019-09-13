<?php


namespace Features\Dqf\Service\Struct\Request ;

use Features\Dqf\Service\Struct\BaseRequestStruct;
use Features\Dqf\Service\Struct\ISessionBasedRequestStruct;
use Features\Dqf\Utils\Functions;
use LengthException;
use Segments_SegmentStruct;

class SourceSegmentsBatchRequestStruct extends BaseRequestStruct implements ISessionBasedRequestStruct {
    const BATCH_LIMIT = 100;

    public $projectId ;
    public $fileId ;

    public $sessionId ;
    public $apiKey ;
    public $projectKey ;

    protected $_sourceSegments ;

    /**
     * @return array
     */
    public function getHeaders() {
        return $this->toArray(['sessionId', 'apiKey', 'projectKey']);
    }

    /**
     * @return array
     */
    public function getPathParams() {
        return [ 'projectId' => $this->projectId, 'fileId' => $this->fileId ] ;
    }

    /**
     * @return array
     */
    public function getBody() {
        return [ 'sourceSegments' => $this->_sourceSegments ];
    }

    public function appendSegment( Segments_SegmentStruct $segment ) {

        if ( count( $this->_sourceSegments ) > self::BATCH_LIMIT ) {
            throw new LengthException('batch size limit exceeded');
        }

        $this->_sourceSegments[] = [
                'sourceSegment' => $segment->segment,
                'index'         => $segment->id,
                'clientId'      => Functions::scopeId( $segment->id )
        ];
    }

}