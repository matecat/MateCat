<?php



namespace Features\Dqf\Model ;

use Exceptions\NotFoundError;
use Features\Dqf\Service\MasterProject;
use Features\Dqf\Service\MasterProjectFiles;
use Features\Dqf\Service\Struct\CreateProjectResponseStruct;
use Features\Dqf\Service\Struct\MasterFileRequestStruct;
use Features\Dqf\Service\Struct\ProjectCreationStruct;
use Features\Dqf\Service\Struct\ProjectRequestStruct;
use Features\Dqf\Utils\UserMetadata;
use Features\Dqf\Utils\ProjectMetadata ;
use Projects_ProjectStruct ;

use Features\Dqf\Service\Client ;

class ProjectCreation {

    /**
     * @var Projects_ProjectStruct
     */
    protected $project ;

    protected $current_state ;

    protected $logger ;

    /**
     * @var Client
     */
    protected $client ;

    /**
     * @var CreateProjectResponseStruct
     */
    protected $remoteProject ;

    /**
     * @var ProjectCreationStruct
     */
    protected $inputStruct ;

    public function __construct( ProjectCreationStruct $struct ) {
        $this->inputStruct = $struct  ;
        $this->project = \Projects_ProjectDao::findById( $struct->id_project );
    }

    public function setLogger($logger) {
        $this->logger = $logger  ;
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

        $this->_initClient() ;
        $this->_createProject();
        $this->_submitProjectFiles();
        $this->_submitSourceSegments();


    }

    protected function _createProject() {
        $projectInputParams = ProjectMetadata::extractProjectParameters( $this->project->getMetadataAsKeyValue() );

        $params = new ProjectRequestStruct(array_merge( array(
                'name' => $this->project->name,
                'sourceLanguageCode' => $this->inputStruct->source_language,
                'cliendId' => $this->project->id,
                'templateName' => '',
                'tmsProjectKey' => ''
        ), $projectInputParams ) );

        $project = new MasterProject($this->client);
        $this->remoteProject = $project->create( $params ) ;

    }

    protected function _submitProjectFiles() {
        $files = \Files_FileDao::getByProjectId($this->project->id) ;
        $filesSubmit = new MasterProjectFiles( $this->client, $this->remoteProject ) ;

        foreach( $files as $file ) {
            $segmentsCount = $this->inputStruct->file_segments_count[ $file->id ];
            $filesSubmit->setFile( $file, $segmentsCount );
        }

        $filesSubmit->send();
    }

    protected function _getCredentials() {
        $user = ( new \Users_UserDao() )->getByEmail( $this->project->id_customer );

        if ( !$user ) {
            throw new NotFoundError("User not found") ;
        }

        return UserMetadata::extractCredentials( $user->getMetadataAsKeyValue() );
    }

    protected function _initClient() {
        list( $username, $password ) = $this->_getCredentials();
        $this->client = new Client();
        $this->client->setCredentials( $username, $password );
    }

    protected function _submitSourceSegments() {


    }

    protected function _submitTargetLanguages() {

    }

    public function submitChildProjects() {

    }

    public function submitChildProjectTargetLanguage() {

    }

    public function submitReviewSettings() {

    }


    protected function closeProjectCreation() {

    }



}