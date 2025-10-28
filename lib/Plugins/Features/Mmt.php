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

    const string FEATURE_CODE = 'mmt';

    protected bool $forceOnProject = true;

    /**
     * Called in @see Bootstrap::notifyBootCompleted
     */
    public static function bootstrapCompleted() {
        EngineConstants::setInEnginesList( EngineConstants::MMT );
    }

    /**
     * Called in
     *
     * @param EngineStruct $newCreatedDbRowStruct
     *
     * @param UserStruct   $userStruct
     *
     * @return EngineStruct
     * @throws MMTServiceApiException
     * @throws ReflectionException
     * @throws Exception
     *
     * @see engineController::add()
     */
    public static function postEngineCreation( EngineStruct $newCreatedDbRowStruct, UserStruct $userStruct ): EngineStruct {

        if ( !$newCreatedDbRowStruct instanceof MMTStruct ) {
            return $newCreatedDbRowStruct;
        }

        /** @var MMTEngine $newTestCreatedMT */
        try {
            $newTestCreatedMT = EnginesFactory::createTempInstance( $newCreatedDbRowStruct );
        } catch ( Exception $exception ) {
            throw new Exception( "MMT license not valid" );
        }

        // Check the account
        try {
            $checkAccount = $newTestCreatedMT->checkAccount();

            if ( !isset( $checkAccount[ 'billingPeriod' ][ 'planForCatTool' ] ) ) {
                throw new Exception( "MMT license not valid" );
            }

            $planForCatTool = $checkAccount[ 'billingPeriod' ][ 'planForCatTool' ];

            if ( $planForCatTool === false ) {
                throw new Exception( "The ModernMT license you entered cannot be used inside CAT tools. Please subscribe to a suitable license to start using the ModernMT plugin." );
            }

        } catch ( Exception $e ) {
            ( new EngineDAO( Database::obtain() ) )->delete( $newCreatedDbRowStruct );

            throw new Exception( $e->getMessage(), $e->getCode() );
        }

        try {

            // if the MMT-preimport flag is enabled,
            // then all the user's MyMemory keys must be sent to MMT
            // when the engine is created
            if ( !empty( $newTestCreatedMT->extra_parameters[ 'MMT-preimport' ] ) ) {
                $newTestCreatedMT->connectKeys( self::_getKeyringOwnerKeysByUid( $userStruct->uid ) );
            }

        } catch ( Exception $e ) {
            ( new EngineDAO( Database::obtain() ) )->delete( $newCreatedDbRowStruct );
            throw $e;
        }

        $UserMetadataDao = new MetadataDao();
        $UserMetadataDao->set( $userStruct->uid, MMTEngine::class, $newCreatedDbRowStruct->id );

        return $newCreatedDbRowStruct;

    }

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
    private static function _getKeyringOwnerKeysByUid( int $uid ): array {

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
     * @param bool   $isValid
     *
     * @param object $data      [
     *                          'providerName' => '',
     *                          'logged_user'  => UserStruct,
     *                          'engineData'   => []
     *                          ]
     *
     * @return EngineStruct|bool
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws ValidationError
     *
     * @see EngineController
     *
     */
    public function buildNewEngineStruct( bool $isValid, object $data ) {

        if ( strtolower( EngineConstants::MMT ) == $data->providerName ) {

            /**
             * @var $featureSet FeatureSet
             */
            $featureSet = $data->featureSet;

            /**
             * @var $logged_user UserStruct
             */
            $logged_user = $data->logged_user;

            /**
             * Create a record of type MMT
             */
            $newEngineStruct = MMTStruct::getStruct();

            $newEngineStruct->uid                                        = $logged_user->uid;
            $newEngineStruct->type                                       = EngineConstants::MT;
            $newEngineStruct->extra_parameters[ 'MMT-License' ]          = $data->engineData[ 'secret' ];
            $newEngineStruct->extra_parameters[ 'MMT-pretranslate' ]     = $data->engineData[ 'pretranslate' ];
            $newEngineStruct->extra_parameters[ 'MMT-preimport' ]        = $data->engineData[ 'preimport' ];
            $newEngineStruct->extra_parameters[ 'MMT-context-analyzer' ] = $data->engineData[ 'context_analyzer' ];

            return $featureSet->filter( 'disableMMTPreimport', (object)[
                    'logged_user'     => $logged_user,
                    'newEngineStruct' => $newEngineStruct
            ] );
        }

        return $isValid;

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
            $engineStruct = $MMTEngine->getEngineRecord();

            $extraParams = $engineStruct->getExtraParamsAsArray();
            $preImport   = $extraParams[ 'MMT-preimport' ];

            if ( $preImport === true ) {
                $MMTEngine->connectKeys( $memoryKeyStructs );
            }
        }
    }
}

