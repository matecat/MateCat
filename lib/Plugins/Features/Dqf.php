<?php

namespace Features;

use API\V2\Exceptions\AuthenticationError;
use BasicFeatureStruct;
use Features;
use Features\Dqf\Service\Struct\ProjectCreationStruct;
use FeatureSet;
use INIT;
use Log;
use Monolog\Logger;

use Features\Dqf\Utils\ProjectMetadata ;
use Users_UserDao;
use Utils;
use WorkerClient;
use AMQHandler ;

class Dqf extends BaseFeature {

    const FEATURE_CODE = 'dqf' ;

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

    public function filterCreateProjectFeatures( $features, $postInput ) {
        if ( isset( $postInput[ 'dqf' ] ) && $postInput['dqf'] == true ) {
            $features[] = new BasicFeatureStruct(['feature_code' => Features::DQF ]);
        }
        return $features ;
    }

    /**
     * @param $projectStructure
     */
    public function postProjectCreate( $projectStructure ) {
        $struct = new ProjectCreationStruct([
            'id_project'           => $projectStructure['id_project'],
            'source_language'      => $projectStructure['source_language'],
            'file_segments_count'  => $projectStructure['file_segments_count']
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

        $metadata = $user->getMetadataAsKeyValue();
        if ( ! ( isset( $metadata['dqf_username'] ) && isset( $metadata['dqf_password'] ) ) ) {
            $projectStructure['result']['errors'][] = $error_user_not_set  ;
            return ;
        }

        $session = new Features\Dqf\Service\Session($metadata['dqf_username'], $metadata['dqf_password']);
        $error_on_remote_login = [ 'code' => -1000, 'message' => 'DQF credentials are not correct.' ];
        try {
            $session->login() ;
        } catch( AuthenticationError $e ) {
            $projectStructure['result']['errors'][] = $error_on_remote_login ;
            return ;
        }

        // At this point we are sure ReviewImproved::loadAndValidateModelFromJsonFile was called already
        // @see FeatureSet::getSortedFeatures

        if ( $projectStructure['features']['review_improved']['__meta']['qa_model'] ) {

        }

        Log::doLog( 'DQF 2 validateProjectCreation -------------------- ' );
        Log::doLog( $projectStructure['result']['errors'] );
    }


}