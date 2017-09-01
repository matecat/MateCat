<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/03/2017
 * Time: 11:55
 */

namespace Features\Dqf\Service;

use Exception;
use Features\Dqf\Service\Struct\CreateProjectResponseStruct;
use Features\Dqf\Service\Struct\Request\SourceSegmentsBatchRequestStruct;
use Features\Dqf\Service\Struct\Response\MaserFileCreationResponseStruct;
use Features\Dqf\Service\Struct\Response\SourceSegmentsBatchResponseStruct;
use Features\Dqf\Utils\Functions;
use Segments_SegmentDao;

class MasterProjectSegmentsBatch {

    /**
     * @var Session
     */
    protected $session ;

    /**
     * @var CreateProjectResponseStruct
     */
    protected $remoteProject ;
    /**
     * @var MaserFileCreationResponseStruct[]
     *
     */
    protected $remoteFiles ;

    /**
     * @var SourceSegmentsBatchRequestStruct[]
     *
     */
    protected $batchRequests ;

    public function __construct(ISession $session, CreateProjectResponseStruct $remoteProject, $remoteFiles) {
        $this->remoteProject = $remoteProject ;
        $this->session       = $session ;
        $this->remoteFiles   = $remoteFiles ;
    }

    public function getResult() {
        $this->_prepareStructs() ;
        $result = $this->_processRequests();
        return $result ;
    }

    public function _prepareStructs() {
        $this->batchRequests = [];

        foreach( $this->remoteFiles as $localFileId => $file ) {
            $segments = ( new Segments_SegmentDao())->getByFileId( Functions::descope( $localFileId ), ['id', 'segment'] ) ;

            $chunked_segments = array_chunk( $segments, SourceSegmentsBatchRequestStruct::BATCH_LIMIT, true );

            foreach( $chunked_segments as $chunk ) {
                $request = new SourceSegmentsBatchRequestStruct() ;
                $request->sessionId  = $this->session->getSessionId();

                $request->projectKey = $this->remoteProject->dqfUUID ;
                $request->projectId  = $this->remoteProject->dqfId ;
                $request->fileId     = $file->dqfId ;

                foreach( $chunk as $segment ) {
                    $request->appendSegment( $segment );
                }

                $this->batchRequests[] = $request ;
            }
        }
    }

    /**
     * @return SourceSegmentsBatchResponseStruct[]
     *
     * @throws Exception
     */
    protected  function _processRequests() {
        $resources = [];
        $client = new Client() ;

        foreach( $this->batchRequests as $request ) {
            $resources[] = $client->createResource(
                    '/project/master/%s/file/%s/sourceSegment/batch', 'post', [
                            'json'       => $request->getBody(),
                            'pathParams' => $request->getPathParams(),
                            'headers'    => $this->session->filterHeaders( $request )
                    ]
            );
        }

        $client->curl()->multiExec();

        if ( count( $client->curl()->getErrors() ) > 0 ) {
            throw new Exception( 'Error during master segments batch ' .
                    var_export( $client->curl()->getAllContents(), true )
            ) ;
        }

        return array_map( function( $item ) {
            return new SourceSegmentsBatchResponseStruct(
                    json_decode( $item, true )
            ) ;
        }, $client->curl()->getAllContents() );

    }
}