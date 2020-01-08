<?php

namespace Features;

use AbstractControllers\IController;
use AMQHandler;
use API\V2\Exceptions\AuthenticationError;
use BasicFeatureStruct;
use Chunks_ChunkStruct;
use Exceptions\ValidationError;
use Features;
use Features\Dqf\Model\RevisionChildProject;
use Features\Dqf\Model\TranslationChildProject;
use Features\Dqf\Model\UserModel;
use Features\Dqf\Service\Struct\ProjectCreationStruct;
use Features\Dqf\Utils\ProjectMetadata;
use Features\ProjectCompletion\CompletionEventStruct;
use Features\ReviewExtended\Model\ArchivedQualityReportModel;
use INIT;
use Klein\Klein;
use Monolog\Logger;
use PHPTALWithAppend;
use Projects_ProjectStruct;
use Users_UserDao;
use Users_UserStruct;
use Utils;
use WorkerClient;

class Dqf extends BaseFeature {

    const FEATURE_CODE = 'dqf' ;
    const INTERMEDIATE_PROJECT_METADATA_KEY = 'dqf_intermediate_project' ;
    const INTERMEDIATE_USER_METADATA_KEY    = 'dqf_intermediate_user' ;

    protected $autoActivateOnProject = false ;

    public static $dependencies = [
            Features::PROJECT_COMPLETION,
            Features::REVIEW_EXTENDED,
            Features::TRANSLATION_VERSIONS
    ] ;

    /**
     * @var Logger
     */
    protected static $logger ;

    /**
     * @return \Monolog\Logger
     */
    public static function staticLogger() {
        if ( is_null( self::$logger ) ) {
            $feature = new BasicFeatureStruct(['feature_code' => self::FEATURE_CODE ]);
            self::$logger = ( new Dqf($feature) )->getLogger();
        }
        return self::$logger ;
    }

    /**
     * @param PHPTALWithAppend $template
     * @param IController      $controller
     */
    public function decorateTemplate( PHPTALWithAppend $template, IController $controller ) {
        Features\Dqf\Utils\Functions::commonVarsForDecorator($template);
    }

    public function filterUserMetadataFilters($filters, $metadata) {
        if ( isset( $metadata['dqf_username'] ) || isset( $metadata['dqf_password'] ) ) {
            $filters['dqf_username'] = array( 'filter' => FILTER_SANITIZE_STRING ) ;
            $filters['dqf_password'] = array( 'filter' => FILTER_SANITIZE_STRING ) ;
        }

        return $filters ;
    }

    public function filterValidateUserMetadata( $metadata, $params ) {
        $user = $params['user'] ;

        if ( !empty($metadata['dqf_username']) && !empty($metadata['dqf_password']) )  {
            $session = new Features\Dqf\Service\Session($metadata['dqf_username'], $metadata['dqf_password'] )  ;
            try {
                $session->login();
            } catch(AuthenticationError $e) {
                throw new ValidationError('DQF credentials are not valid') ;
            }
        }
        return $metadata ;
    }

    /**
     * @param $inputFilter
     *
     * @return array
     */
    public function filterCreateProjectInputFilters( $inputFilter ) {
        return array_merge( $inputFilter, ProjectMetadata::getInputFilter() ) ;
    }

    /**
     * @param $metadata
     * @param $options
     *
     * @return array
     */
    public function createProjectAssignInputMetadata( $metadata, $options ) {
        $options = Utils::ensure_keys( $options, array('input'));

        $my_metadata = array_intersect_key( $options['input'], array_flip( ProjectMetadata::$keys ) ) ;
        $my_metadata = array_filter( $my_metadata ); // <-- remove all `empty` array elements

        return  array_merge( $my_metadata, $metadata ) ;
    }

    /**
     * @param Chunks_ChunkStruct                      $chunk
     * @param ProjectCompletion\CompletionEventStruct $params
     * @param                                         $lastId
     */
    public function project_completion_event_saved( Chunks_ChunkStruct $chunk, CompletionEventStruct $params, $lastId ) {
        // at this point we have to enqueue delivery to DQF of the translated or reviewed segments
        // TODO: put this in a queue for background processing
        if ( ! $params->is_review ) {
            $translationChildProject = new TranslationChildProject( $chunk ) ;
            $translationChildProject->createRemoteProjectsAndSubmit();
            $translationChildProject->setCompleted();
        }
    }

    public function archivedQualityReportSaved( ArchivedQualityReportModel $archivedQRModel ) {

        $revisionChildModel = new RevisionChildProject(
                $archivedQRModel->getChunk(),
                $archivedQRModel->getSavedRecord()->version
        ) ;

        $revisionChildModel->createRemoteProjectsAndSubmit() ;
        $revisionChildModel->setCompleted();
    }

    public function filterCreationStatus($result, Projects_ProjectStruct $project) {
        $master_project_created = $project->getMetadataValue('dqf_master_project_creation_completed_at');

        if ( $master_project_created ) {
            return $result ;
        }

        return null;
    }

    /**
     * @param $features
     * @param $controller \NewController|\createProjectController
     *
     * @return mixed
     * @throws ValidationError
     */
    public function filterCreateProjectFeatures( $features, $controller ) {
        if ( isset( $controller->postInput[ 'dqf' ] ) && !!$controller->postInput[ 'dqf' ] ) {
            $validationErrors = ProjectMetadata::getValiationErrors( $controller->postInput ) ;

            if ( !empty( $validationErrors ) ) {
                throw new ValidationError('input validation failed: ' . implode(', ', $validationErrors ) ) ;
            }

            $features[ Features::DQF ] = new BasicFeatureStruct([ 'feature_code' => Features::DQF ]);
        }
        return $features ;
    }

    public function filterNewProjectInputFilters( $inputFilter ) {
        return array_merge( $inputFilter, ProjectMetadata::getInputFilter() ) ;
    }

    /**
     * @param $projectStructure
     */
    public function postProjectCommit( $projectStructure ) {
        $struct = new ProjectCreationStruct([
            'id_project'          => $projectStructure['id_project'],
            'source_language'     => $projectStructure['source_language'],
            'file_segments_count' => $projectStructure['file_segments_count']
        ]);

        WorkerClient::init( new AMQHandler() );
        WorkerClient::enqueue( 'DQF', '\Features\Dqf\Worker\CreateProjectWorker', $struct->toArray() );
    }

    public static function loadRoutes( Klein $klein ) {

    }

    /**
     * Define if a project is completable.
     *
     * @param                    $value
     * @param Chunks_ChunkStruct $chunk
     * @param Users_UserStruct   $user
     *
     * @return bool
     */
    public function filterJobCompletable($value, Chunks_ChunkStruct $chunk, Users_UserStruct $user, $isRevision) {
        $authModel = new Features\Dqf\Model\CatAuthorizationModel($chunk, $isRevision );
        return $value && $authModel->isUserAuthorized( $user ) ;
    }

    /**
     * Check the input metadata array to see if this feature is enabled for a given project.
     * If so, include the project dependencies in the list.
     *
     * @param $dependencies
     * @param $metadata
     *
     * @return array
     */
    public function filterProjectDependencies( $dependencies, $metadata ) {
        if ( isset( $metadata[ self::FEATURE_CODE ] ) && $metadata[ self::FEATURE_CODE ] ) {
            $dependencies = array_merge( $dependencies, static::getDependencies() );
        }
        return $dependencies ;
    }

    public function validateProjectCreation( $projectStructure ) {

        if ( count( $projectStructure[ 'target_language' ] ) > 1 ) {
            $multilang_error = [ 'code' => -1000, 'message' => 'Cannot create multilanguage projects when DQF option is enabled' ];
            $projectStructure['result']['errors'][] = $multilang_error ;
            return ;
        }

        if ( $projectStructure['metadata'] ) {
            // TODO: other incoming DQF related options to be validated
        }

        $error_user_not_set = [ 'code' => -1000, 'message' => 'DQF user is not set' ];

        if ( empty( $projectStructure['id_customer'] ) ) {
            $projectStructure['result']['errors'][] = $error_user_not_set  ;
            return ;
        }

        $user = ( new Users_UserDao() )->setCacheTTL(3600)->getByEmail( $projectStructure['id_customer'] ) ;

        if ( !$user ) {
            $projectStructure['result']['errors'][] = $error_user_not_set  ;
            return ;
        }

        $dqfUser = new UserModel( $user ) ;

        if ( ! $dqfUser->hasCredentials() ) {
            $projectStructure['result']['errors'][] = $error_user_not_set  ;
            return ;
        }

        $error_on_remote_login = [ 'code' => -1000, 'message' => 'DQF credentials are not correct.' ];
        if ( ! $dqfUser->validCredentials() ) {
            $projectStructure['result']['errors'][] = $error_on_remote_login ;
            return ;
        }

        // At this point we are sure ReviewExtended::loadAndValidateModelFromJsonFile was called already
        // @see FeatureSet::getSortedFeatures

        if ( $projectStructure['features']['review_extended']['__meta']['qa_model'] ) {
            // override QA model
            $projectStructure['features']['review_extended']['__meta']['qa_model'] = json_decode(
                    file_get_contents( INIT::$ROOT . '/inc/dqf/qa_model.json' ), true
            );
        }
    }

    /**
     * DQF projects can be splitted only one time.
     *
     * @param $projectStructure
     */
    public function postJobSplitted( $projectStructure ) {

    }

}