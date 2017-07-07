<?php



namespace Features\Dqf\Model ;

use Exceptions\NotFoundError;
use Features\Dqf\Service\ChildProject;
use Features\Dqf\Service\MasterProject;
use Features\Dqf\Service\MasterProjectFiles;
use Features\Dqf\Service\MasterProjectReviewSettings;
use Features\Dqf\Service\Struct\CreateProjectResponseStruct;
use Features\Dqf\Service\MasterProjectSegmentsBatch;
use Features\Dqf\Service\Struct\ProjectCreationStruct;
use Features\Dqf\Service\Struct\ProjectRequestStruct;
use Features\Dqf\Service\Struct\Response\MasterFileResponseStruct;
use Features\Dqf\Service\Session ;


use Features\Dqf\Service\Struct\Response\ReviewSettingsResponseStruct;
use Features\Dqf\Utils\Functions;
use Features\Dqf\Utils\UserMetadata;
use Features\Dqf\Utils\ProjectMetadata ;
use Files_FileDao;
use Projects_ProjectDao;
use Projects_ProjectStruct ;

class ProjectCreation {

    /**
     * @var Projects_ProjectStruct
     */
    protected $project ;

    protected $current_state ;

    protected $logger ;

    /**
     * @var Session
     */
    protected $session ;

    /**
     * @var CreateProjectResponseStruct
     */
    protected $remoteProject ;

    /**
     * @var ProjectCreationStruct
     */
    protected $inputStruct ;

    /**
     * @var MasterFileResponseStruct[]
     */
    protected $remoteFiles ;

    /**
     * @var array
     */
    protected $segmentsBatchResult ;

    /**
     * @var array
     */
    protected $childProjects ;

    /**
     * @var ReviewSettingsResponseStruct ;
     */
    protected $reviewSettings ;

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
        /**
         *
         * - Creation of master project (http://dqf-api.ta-us.net/#!/Project%2FMaster/add)
         * - Submit of project files (http://dqf-api.ta-us.net/#!/Project%2FMaster%2FFile/add)
         * - Submit of project’s source segments (http://dqf-api.ta-us.net/#!/Project%2FMaster%2FFile%2FSource_segment/add)
         * - Submit of project’s target languages (http://dqf-api.ta-us.net/#!/Project%2FMaster%2FFile%2FTarget_Language/add)
         * - Submit of one child project per target language (http://dqf-api.ta-us.net/#!/Project%2FChild/add)
         * - Submit the child project’s target language (http://dqf-api.ta-us.net/#!/Project%2FChild%2FFile%2FTarget_Language/add)
         * - Submit reviewSettings to be used throughout the whole project lifecycle
         *
         */

        $this->_initSession();
        $this->_createProject();
        $this->_submitProjectFiles();
        $this->_submitSourceSegments();
        $this->_submitChildProjects();
        $this->_submitReviewSettings();
    }

    protected function _createProject() {
        $projectInputParams = ProjectMetadata::extractProjectParameters( $this->project->getMetadataAsKeyValue() );

        $params = new ProjectRequestStruct(array_merge( array(
                'name'               => $this->project->name,
                'sourceLanguageCode' => $this->inputStruct->source_language,
                'clientId'           => Functions::scopeId( $this->project->id ),
                'templateName'       => '',
                'tmsProjectKey'      => ''
        ), $projectInputParams ) );

        $project = new MasterProject($this->session);
        $this->remoteProject = $project->create( $params ) ;
    }

    protected function _submitProjectFiles() {
        $files = Files_FileDao::getByProjectId($this->project->id) ;
        $filesSubmit = new MasterProjectFiles( $this->session, $this->remoteProject ) ;

        foreach( $files as $file ) {
            $segmentsCount = $this->inputStruct->file_segments_count[ $file->id ];
            $filesSubmit->setFile( $file, $segmentsCount );
        }

        $filesSubmit->setTargetLanguages( $this->project->getTargetLanguages() );

        $this->remoteFiles = $filesSubmit->getRemoteFiles();
    }

    protected function _submitReviewSettings() {
        $dqfQaModel = new DqfQualityModel( $this->project ) ;
        $request = new MasterProjectReviewSettings( $this->session, $this->remoteProject );

        $struct = $dqfQaModel->getReviewSettings() ;

        $this->reviewSettings = $request->create( $struct );
    }

    protected function _getCredentials() {
        $user = ( new \Users_UserDao() )->getByEmail( $this->project->id_customer );

        if ( !$user ) {
            throw new NotFoundError("User not found") ;
        }

        return UserMetadata::extractCredentials(  $user->getMetadataAsKeyValue() );
    }

    protected function _initSession() {
        list( $username, $password ) = $this->_getCredentials();
        $this->session = new Session( $username, $password );
        $this->session->login();
    }

    protected function _submitSourceSegments() {
        $batchSegments = new MasterProjectSegmentsBatch($this->session, $this->remoteProject, $this->remoteFiles);
        $this->segmentsBatchResult = $batchSegments->getResult() ;
    }

    protected function _submitChildProjects() {
        $this->childProjects = [] ;

        foreach( $this->project->getChunks() as $chunk ) {
            $childProject = new ChildProject($this->session, $this->remoteProject, $chunk ) ;
            $this->childProjects[ $chunk->getIdentifier() ] =
                    $childProject->createTranslationChild( $this->remoteFiles );
        }
    }

}