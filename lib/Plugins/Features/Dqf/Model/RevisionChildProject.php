<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 08/08/2017
 * Time: 15:41
 */

namespace Features\Dqf\Model;

use Chunks_ChunkStruct;
use DataAccess\LoudArray;
use Exception;
use Features\Dqf\Service\ChildProjectRevisionBatchService;
use Features\Dqf\Service\Struct\Request\RevisionRequestStruct;
use Features\ReviewExtended\Model\QualityReportModel;


/**
 * Class RevisionChildProject
 *
 * TODO: this class has many similarities with TranslationChildProject, consider abstracting a common parent or some
 * other refactoring to reduce code duplication.
 *
 * @package Features\Dqf\Model
 */
class RevisionChildProject extends AbstractChildProject {

    protected $qualityModel;

    protected $version;

    public function __construct( Chunks_ChunkStruct $chunk, $version ) {
        parent::__construct( $chunk, DqfProjectMapDao::PROJECT_TYPE_REVISE );

        $this->version  = $version ;
    }

    protected function _submitData() {
        $this->files = $this->chunk->getFiles() ;

        $requestStructs = [] ;

        foreach( $this->files as $file ) {
            list ( $fileMinIdSegment, $fileMaxIdSegment ) = $file->getMaxMinSegmentBoundariesForChunk( $this->chunk );

            $segmentIdsMap = new LoudArray( ( new DqfSegmentsDao() )->getByIdSegmentRange( $fileMinIdSegment, $fileMaxIdSegment ) );
            $remoteFileId = $this->_findRemoteFileId( $file );

            $dqfChildProjects = $this->dqfProjectMapResolver->reload()->getCurrentInSegmentIdBoundaries(
                    $fileMinIdSegment, $fileMaxIdSegment
            );

            foreach ( $dqfChildProjects as $dqfChildProject ) {

                $segmentsWithIssues = $this->getSegmentsWithIssues( $dqfChildProject->first_segment, $dqfChildProject->last_segment );

                foreach( $segmentsWithIssues as $segment ) {

                    // segments can have multiple issues, create one struct per issue.
                    $requestStruct = new RevisionRequestStruct() ;

                    $requestStruct->fileId         = $remoteFileId ;
                    $requestStruct->targetLangCode = $this->chunk->target  ;
                    $requestStruct->sessionId      = $this->userSession->getSessionId() ;
                    // $requestStruct->apiKey         = INIT::$DQF_API_KEY ;
                    $requestStruct->projectKey     = $dqfChildProject->dqf_project_uuid ;
                    $requestStruct->projectId      = $dqfChildProject->dqf_project_id ;

                    $requestStruct->translationId = $segmentIdsMap[ $segment['id'] ] ['dqf_translation_id'] ;

                    foreach( $segment['issues'] as $issue ) {
                        $requestStruct->addError( $issue ) ;
                    }

                    $requestStructs [] = $requestStruct ;
                }
            }
        }

        // Don't overload the multicurl
        // TODO: this should be moved in the service class. This is detail of the communication service.
        foreach( array_chunk( $requestStructs, 50 )  as $chunk ) {
            $service = new ChildProjectRevisionBatchService( $this->userSession ) ;
            foreach( $chunk as $item ) {
                $service->addRevision( $item );
            }
            $service->process();
        }
    }


    protected function getSegmentsWithIssues($min, $max) {
        if ( $min > $max ) {
            throw new Exception('min is higher than max' ) ;
        }

        $this->qualityModel = new QualityReportModel( $this->chunk );
        $this->qualityModel->setVersionNumber( $this->version );
        $this->qualityModel->getStructure() ; // terrible API

        return array_filter( $this->qualityModel->getAllSegments(), function( $item ) use ( $max, $min ) {
            return $item['id'] <= $max && $item['id'] >= $min && count( $item['issues'] ) > 0 ;
        }) ;
    }


}