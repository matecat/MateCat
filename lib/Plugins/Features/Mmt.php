<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 04/08/17
 * Time: 16.31
 *
 */

namespace Plugins\Features;


use Controller\Abstracts\KleinController;
use Controller\API\App\CreateProjectController;
use Controller\API\App\EngineController;
use Controller\API\App\UserKeysController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\V1\NewController;
use Exception;
use Model\DataAccess\Database;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use Model\Engines\Structs\MMTStruct;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\TmKeyManagement\MemoryKeyDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\MetadataDao;
use Model\Users\UserStruct;
use ReflectionException;
use Utils\Constants\EngineConstants;
use Utils\Engines\AbstractEngine;
use Utils\Engines\EnginesFactory;
use Utils\Engines\MMT as MMTEngine;
use Utils\Engines\MMT\MMTServiceApiException;
use Utils\Logger\LoggerFactory;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;
use Utils\TmKeyManagement\TmKeyManager;

class Mmt extends BaseFeature {

    const FEATURE_CODE = 'mmt';

    protected bool $forceOnProject = true;

    /**
     * Called in
     *
     * @param array          $config
     * @param AbstractEngine $engine
     * @param JobStruct      $jobStruct
     *
     * @return array
     * @throws Exception
     * @see getContributionController::doAction()
     *
     */
    public static function beforeGetContribution( array $config, AbstractEngine $engine, JobStruct $jobStruct ): array {

        if ( $engine instanceof MMTEngine ) {

            //get the Owner Keys from the Job
            $tm_keys          = TmKeyManager::getOwnerKeys( [ $jobStruct->tm_keys ], 'r' );
            $config[ 'keys' ] = array_map( function ( $tm_key ) {
                /**
                 * @var $tm_key MemoryKeyStruct
                 */
                return $tm_key->key;
            }, $tm_keys );

            $jobsMetadataDao = new \Model\Jobs\MetadataDao();
            $contextRs       = $jobsMetadataDao->setCacheTTL( 60 * 60 * 24 * 30 )->getByIdJob( $jobStruct->id, 'mt_context' );
            $mt_context      = @array_pop( $contextRs );

            if ( !empty( $mt_context ) ) {
                $config[ 'mt_context' ] = $mt_context->value;
            }

            $config[ 'job_id' ]     = $jobStruct->id;
            $config[ 'secret_key' ] = self::getG2FallbackSecretKey();
            $config[ 'priority' ]   = 'normal';

        }

        return $config;

    }

    public static function getG2FallbackSecretKey() {
        $secret_key       = [ 'secret_key' => null ];
        $config_file_path = realpath( AppConfig::$ROOT . '/inc/mmt_fallback_key.ini' );
        if ( file_exists( $config_file_path ) ) {
            $secret_key = parse_ini_file( $config_file_path );
        }

        return $secret_key[ 'secret_key' ];
    }

    /**
     * @param int $uid
     *
     * @return MemoryKeyStruct[]
     */
    public static function getKeyringOwnerKeysByUid( int $uid ): array {

        /*
         * Take the keys of the user
         */
        try {
            $_keyDao = new MemoryKeyDao( Database::obtain() );
            $dh      = new MemoryKeyStruct( [ 'uid' => $uid ] );
            $keyList = $_keyDao->read( $dh );
        } catch ( Exception $e ) {
            $keyList = [];
            LoggerFactory::doJsonLog( $e->getMessage() );
        }

        return $keyList;
    }

    /**
     * Called in
     *
     * @param array                                 $projectFeatures
     * @param NewController|CreateProjectController $controller
     * @param int                                   $mt_engine_id
     *
     * @return array
     * @throws Exception
     * @see NewController::__appendFeaturesToProject()
     *
     * @see createProjectController::__appendFeaturesToProject()
     */
    public function filterCreateProjectFeatures( array $projectFeatures, KleinController $controller, int $mt_engine_id ): array {

        $engine = EnginesFactory::getInstance( $mt_engine_id );
        if ( $engine instanceof MMTEngine ) {
            $feature               = new BasicFeatureStruct();
            $feature->feature_code = self::FEATURE_CODE;
            $projectFeatures[]     = $feature;
        }

        return $projectFeatures;

    }

    /**
     * C@param                  $memoryKeyStructs MemoryKeyStruct[]
     *
     * @param                  $uid              integer
     *
     * @throws Exception
     * @throws MMTServiceApiException
     * @see      \Model\ProjectManager\ProjectManager::setPrivateTMKeys()
     * @see      UserKeysController::newKey()
     *
     * @internal param UserStruct $userStruct
     */
    public function postTMKeyCreation( array $memoryKeyStructs, int $uid ) {

        if ( empty( $memoryKeyStructs ) or empty( $uid ) ) {
            return;
        }

        //retrieve OWNER MMT License
        $ownerMmtEngineMetaData = ( new MetadataDao() )->get( $uid, self::FEATURE_CODE ); // engine_id
        if ( !empty( $ownerMmtEngineMetaData ) ) {

            /**
             * @var MMTEngine $MMTEngine
             */
            $MMTEngine    = EnginesFactory::getInstance( $ownerMmtEngineMetaData->value );
            $MMTEngine->connectKeys( $memoryKeyStructs );

        }
    }
}

