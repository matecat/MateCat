<?php

namespace Features;

use AMQHandler;
use API\V2\Exceptions\AuthenticationError;
use BasicFeatureStruct;
use catController;
use Chunks_ChunkStruct;
use Exceptions\ValidationError;
use Features;
use Features\Dqf\Model\TranslationChildProject;
use Features\Dqf\Model\UserModel;
use Features\Dqf\Service\Struct\ProjectCreationStruct;
use Features\Dqf\Utils\ProjectMetadata;
use Features\ProjectCompletion\CompletionEventStruct;
use INIT;
use Log;
use Monolog\Logger;
use Users_UserDao;
use Utils;
use WorkerClient;

class Dqf extends BaseFeature {

    const FEATURE_CODE = 'dqf' ;

    protected $autoActivateOnProject = false ;

    public static $dependencies = [
            Features::PROJECT_COMPLETION, Features::REVIEW_IMPROVED, Features::TRANSLATION_VERSIONS
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


    public function catControllerDoActionStart( catController $controller ) {
        $controller->setLoginRequired( true ) ;
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
     * These are the dependencies we need to make to be enabled when we detedct DQF is to be
     * activated for a given project. These will fill the project metadata table.
     *
     *
     * @return array
     */
    public function getProjectDependencies() {
        return self::$dependencies ;
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

        $metadata = array_intersect_key( $options['input'], array_flip( ProjectMetadata::$keys ) ) ;
        $metadata = array_filter( $metadata ); // <-- remove all `empty` array elements

        return  $metadata ;
    }

    /**
     * @param Chunks_ChunkStruct                      $chunk
     * @param ProjectCompletion\CompletionEventStruct $params
     * @param                                         $lastId
     */
    public function project_completion_event_saved( Chunks_ChunkStruct $chunk, CompletionEventStruct $params, $lastId ) {
        // at this point we have to enqueue delivery to DQF of the translated or reviewed segments
        if ( $params->is_review ) {
            // enqueue task for review
        }
        else {
            $translationBatch = new TranslationChildProject( $chunk ) ;
            $translationBatch->submitTranslationBatch();
        }
    }

    public function filterCreateProjectFeatures( $features, $postInput ) {
        if ( isset( $postInput[ 'dqf' ] ) && $postInput[ 'dqf' ] == true ) {
            $features[] = new BasicFeatureStruct([ 'feature_code' => Features::DQF ]);
        }
        return $features ;
    }

    /**
     * @param $projectStructure
     */
    public function postProjectCreate( $projectStructure ) {
        $struct = new ProjectCreationStruct([
            'id_project'          => $projectStructure['id_project'],
            'source_language'     => $projectStructure['source_language'],
            'file_segments_count' => $projectStructure['file_segments_count']
        ]);

        WorkerClient::init( new AMQHandler() );
        WorkerClient::enqueue( 'DQF', '\Features\Dqf\Worker\CreateProjectWorker', $struct->toArray() );
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
            $dependencies = array_merge( $dependencies, $this->getProjectDependencies() );
        }
        return $dependencies ;
    }

    public function validateProjectCreation( $projectStructure ) {
        Log::doLog('DQF validateProjectCreation -------------------- ');

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

        // At this point we are sure ReviewImproved::loadAndValidateModelFromJsonFile was called already
        // @see FeatureSet::getSortedFeatures

        if ( $projectStructure['features']['review_improved']['__meta']['qa_model'] ) {
            // override QA model
            $projectStructure['features']['review_improved']['__meta']['qa_model'] = json_decode(
                    file_get_contents( INIT::$ROOT . '/inc/dqf/qa_model.json' ), true
            );
        }
    }


}