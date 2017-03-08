<?php

namespace Features;

use Features\Dqf\Service\Struct\ProjectCreationStruct;
use Monolog\Logger;

use Features\Dqf\Utils\ProjectMetadata ;
use WorkerClient;
use AMQHandler ;

class Dqf extends BaseFeature {

    const FEATURE_CODE = 'dqf' ;

    /**
     * @var Logger
     */
    protected static $logger ;

    public function getDependencies() {
        return array();
    }

    /**
     * These are the dependencies we need to make to be enabled when we detedct DQF is to be
     * activated for a given project. These will fill the project metadata table.
     *
     * @return array
     */
    public function getProjectDependencies() {
        return array(
                \Features::PROJECT_COMPLETION,
                \Features::REVIEW_IMPROVED,
                \Features::TRANSLATION_VERSIONS
        );
    }

    /**
     * @return \Monolog\Logger
     */
    public static function staticLogger() {
        if ( is_null( self::$logger ) ) {
            $feature = new \BasicFeatureStruct(['feature_code' => self::FEATURE_CODE ]);
            self::$logger = ( new Dqf($feature) )->getLogger();
        }
        return self::$logger ;
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
        $options = \Utils::ensure_keys( $options, array('input'));

        $metadata = array_intersect_key( $options['input'], array_flip( ProjectMetadata::$keys ) ) ;
        $metadata = array_filter( $metadata ); // <-- remove all `empty` array elements

        return  $metadata ;
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
        if ( $projectStructure['metadata'] ) {
            // TODO: other incoming DQF related options to be validated,
        }

        $user_error = array( -1000, 'DQF user is not set' );

        if ( empty($projectStructure['id_customer'] ) ) {
            $projectStructure['result']['errors'][] = $user_error  ;
        }

        $user = ( new \Users_UserDao() )->setCacheTTL(3600)->getByEmail( $projectStructure['id_customer'] ) ;

        if ( !$user ) {
            $projectStructure['result']['errors'][] = $user_error  ;
        }

        // TODO: make this iterate thorugh instances of its dependencies, calling validateProjectCreation
        ReviewImproved::loadAndValidateModelFromJsonFile( $projectStructure ) ;

    }


}