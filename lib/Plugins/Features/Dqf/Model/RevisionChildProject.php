<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 08/08/2017
 * Time: 15:41
 */

namespace Features\Dqf\Model;

use Chunks_ChunkStruct;
use Exception;
use Features\Dqf\Service\ChildProjectRevisionBatchService;
use Features\Dqf\Service\FileIdMapping;
use Features\Dqf\Service\ISession;
use Features\Dqf\Service\Struct\CreateProjectResponseStruct;
use Features\Dqf\Service\Struct\Request\RevisionErrorEntryStruct;
use Features\Dqf\Service\Struct\Request\RevisionRequestStruct;
use Features\ReviewImproved\Model\QualityReportModel;
use Files_FileStruct;
use INIT;
use Jobs\MetadataDao;
use LoudArray;
use Users_UserDao;


/**
 * Class RevisionChildProject
 *
 * TODO: this class has many similarities with TranslationChildProject, consider abstracting a common parent or some
 * other refactoring to reduce code duplication.
 *
 * @package Features\Dqf\Model
 */
class RevisionChildProject {

    /** * @var Chunks_ChunkStruct */
    protected $chunk ;

    protected $qualityModel;

    protected $version;

    /** * @var ChildProjectRevisionBatchService */
    protected $revisionService ;

    /** * @var UserModel */
    protected $dqfUser ;

    /** * @var DqfProjectMapStruct[] */
    protected $dqfChildProjects = [];

    protected $parentKeysMap = [] ;

    /** * @var ProjectMapResolverModel */
    protected $dqfProjectMapResolver  ;

    /** * @var ISession */
    private $userSession;

    private $files;

    public function __construct( Chunks_ChunkStruct $chunk, $version ) {
        $this->version  = $version ;
        $this->chunk    = $chunk ;

        $this->_initUserAndSession() ;

        $this->dqfProjectMapResolver = new ProjectMapResolverModel( $this->chunk, 'revise' );
        $this->dqfChildProjects = $this->dqfProjectMapResolver->getMappedProjects();
    }

    public function submitRevisionData() {
        if ( $this->projectCreationRequired() ) {
            $this->createRemoteProjects();
        }
        $this->_submitRevisions() ;
    }

    protected function _submitRevisions() {

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


    protected function getSegmentsWithIssues($max, $min) {
        $this->qualityModel = new QualityReportModel( $this->chunk );
        $this->qualityModel->setVersionNumber( $this->version );
        $this->qualityModel->getStructure() ; // terrible API

        return array_filter( $this->qualityModel->getAllSegments(), function( $item ) use ( $max, $min ) {
            return count( $item['issues'] ) > 0 ;
        }) ;
    }

    protected function createRemoteProjects() {
        $parents = $this->dqfProjectMapResolver->getParents();

        $this->dqfChildProjects = [] ;

        foreach( $parents as $parent ) {
            $struct = new CreateProjectResponseStruct();
            $struct->dqfUUID = $parent->dqf_project_uuid ;
            $struct->dqfId = $parent->dqf_project_id ;

            $project = new ChildProjectCreationModel($struct, $this->chunk, 'revise' );

            $model = new ProjectModel( $parent );

            $project->setUser( $model->getUser() );
            $project->setFiles( $model->getFilesResponseStruct() ) ;

            $project->create();

            $this->dqfChildProjects[]  = $project->getSavedRecord() ;
        }
    }


    protected function projectCreationRequired() {
        return empty( $this->dqfChildProjects ) ;
    }

    public function setCompleted() {

    }

    protected function _initUserAndSession() {
        $uid = ( new MetadataDao() )
                ->get( $this->chunk->id, $this->chunk->password, CatAuthorizationModel::DQF_REVISE_USER )
                ->value ;

        if ( !$uid ) {
            throw new Exception('dqf_revise_user must be set') ;
        }

        $this->dqfUser     = new UserModel( ( new Users_UserDao() )->getByUid( $uid ) );
        $this->userSession = $this->dqfUser->getSession()->login();
    }

    protected function _findRemoteFileId( Files_FileStruct $file ) {
        $projectOwner = new UserModel ( $this->chunk->getProject()->getOwner()  ) ;
        $service = new FileIdMapping( $projectOwner->getSession()->login(), $file ) ;

        return $service->getRemoteId() ;
    }
}