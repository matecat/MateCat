<?php



namespace Features\Dqf\Model ;

use Database;
use Exception;
use Features\Dqf\Service\MasterProject;
use Features\Dqf\Service\MasterProjectFiles;
use Features\Dqf\Service\MasterProjectReviewSettings;
use Features\Dqf\Service\MasterProjectSegmentsBatch;
use Features\Dqf\Service\Session;
use Features\Dqf\Service\Struct\CreateProjectResponseStruct;
use Features\Dqf\Service\Struct\ProjectCreationStruct;
use Features\Dqf\Service\Struct\ProjectRequestStruct;
use Features\Dqf\Service\Struct\Response\MaserFileCreationResponseStruct;
use Features\Dqf\Service\Struct\Response\ReviewSettingsResponseStruct;
use Features\Dqf\Utils\Functions;
use Features\Dqf\Utils\ProjectMetadata;
use Files_FileDao;
use Projects_ProjectDao;
use Projects_ProjectStruct;
use Utils;


class ProjectCreation {

    protected $intermediateRootProjectRequired = false;

    /**
     * @var Projects_ProjectStruct
     */
    protected $project ;

    protected $current_state ;

    protected $logger ;

    /**
     * @var Session
     */
    protected $ownerSession ;

    /**
     * @var CreateProjectResponseStruct
     */
    protected $remoteMasterProject ;

    /**
     * @var ProjectCreationStruct
     */
    protected $inputStruct ;

    /**
     * @var MaserFileCreationResponseStruct[]
     */
    protected $remoteFiles ;

    /**
     * @var array
     */
    protected $segmentsBatchResult ;

    /**
     * @var ReviewSettingsResponseStruct ;
     */
    protected $reviewSettings ;

    /** @var  UserModel */
    protected $user ;

    public function __construct( ProjectCreationStruct $struct ) {
        $this->inputStruct = $struct  ;
        $this->project = Projects_ProjectDao::findById( $struct->id_project );

        // find  back the qa_model file from json?
        // no the quality model must be found trom
    }

    public function setLogger($logger) {
        $this->logger = $logger ;
    }

    public function process() {
        $this->_initSession();
        $this->_createProject();
        $this->_submitProjectFiles();
        $this->_submitReviewSettings();
        $this->_submitSourceSegments();
        $this->_saveCompletion();
    }

    protected function _saveCompletion() {
        $this->project->setMetadata('dqf_master_project_creation_completed_at', time() );
    }

    protected function _createProject() {
        $projectInputParams = ProjectMetadata::extractProjectParameters( $this->project->getMetadataAsKeyValue() );

        /**
         * Generating id_project from a sequence here allows for retrying this step if anything fails.
         * Otherwise we would have a conflict if the master project is created but something goes wrong during later on.
         */
        $id_project = Database::obtain()->nextSequence('id_dqf_project')[ 0 ] ;

        $params = new ProjectRequestStruct(array_merge( array(
                'name'               => $this->project->name,
                'sourceLanguageCode' => $this->inputStruct->source_language,
                'clientId'           => Functions::scopeId( $id_project ),
                'templateName'       => '',
                'tmsProjectKey'      => ''
        ), $projectInputParams ) );

        $project = new MasterProject($this->ownerSession);
        $this->remoteMasterProject = $project->create( $params ) ;

        foreach( $this->project->getChunks() as $chunk ) {
            $struct = new DqfProjectMapStruct([
                    'id'               => $id_project,
                    'id_job'           => $chunk->id,
                    'password'         => $chunk->password,
                    'first_segment'    => $chunk->job_first_segment,
                    'last_segment'     => $chunk->job_last_segment,
                    'dqf_project_id'   => $this->remoteMasterProject->dqfId,
                    'dqf_project_uuid' => $this->remoteMasterProject->dqfUUID,
                    'create_date'      => Utils::mysqlTimestamp( time() ),
                    'project_type'     => 'master',
                    'uid'              => $this->project->getOriginalOwner()->uid
            ]);

            DqfProjectMapDao::insertStructWithAutoIncrements( $struct ) ;
        }
    }

    protected function _submitProjectFiles() {
        $files = Files_FileDao::getByProjectId($this->project->id) ;
        $remoteFiles = new MasterProjectFiles(
                $this->ownerSession,
                $this->remoteMasterProject
        );

        foreach( $files as $file ) {
            $segmentsCount = $this->inputStruct->file_segments_count[ $file->id ];
            $remoteFiles->setFile( $file, $segmentsCount );
        }

        $remoteFiles->setTargetLanguages( $this->project->getTargetLanguages() );

        $this->remoteFiles = $remoteFiles->submitFiles();
    }

    protected function _submitReviewSettings() {
        $dqfQaModel = new DqfQualityModel( $this->project ) ;
        $request = new MasterProjectReviewSettings( $this->ownerSession, $this->remoteMasterProject );

        $struct = $dqfQaModel->getReviewSettings() ;

        $this->reviewSettings = $request->create( $struct );

        /**
         * This check was introduced due to weird errors about missing reviewErrors on
         */
        if ( !empty( $this->reviewSettings->dqfId ) ) {
            $this->project->setMetadata('dqf_review_settings_id', $this->reviewSettings->dqfId ) ;
        }

        else {
            throw new Exception('Dqf review settings where not set. ' .
                    var_export( $this->reviewSettings->toArray(), true )
            ) ;
        }
    }

    protected function _initSession() {
        $this->user = ( new UserModel( $this->project->getOriginalOwner() ) );
        $this->ownerSession = $this->user->getSession()->login() ;
    }

    protected function _submitSourceSegments() {
        $batchSegments = new MasterProjectSegmentsBatch(
                $this->ownerSession,
                $this->remoteMasterProject,
                $this->remoteFiles
        );

        $results = $batchSegments->getResult() ;

        foreach( $results as $result ) {
            if ( empty( $result->segmentList ) ) {
                throw new Exception('segmentList is empty');
            }
            $this->_saveSegmentsList( $result->segmentList ) ;
        }

        $this->project->setMetadata('dqf_source_segments_submitted', 1) ;
    }

    protected function _saveSegmentsList( $segmentList ) {
        $dao = new DqfSegmentsDao() ;
        $dao->insertBulkMap( array_map(function( $item ) {
            return [
                    Functions::descope($item['clientId']),
                    $item['dqfId'],
                    null
            ];
        }, $segmentList ) ) ;
    }
}