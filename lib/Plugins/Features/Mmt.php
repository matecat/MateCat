<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 04/08/17
 * Time: 16.31
 *
 */

namespace Features;


use API\Commons\Exceptions\AuthenticationError;
use BasicFeatureStruct;
use Constants_Engines;
use createProjectController;
use Database;
use Engine;
use Engines\MMT\MMTServiceApiException;
use Engines_AbstractEngine;
use Engines_MMT;
use EnginesModel_EngineDAO;
use EnginesModel_EngineStruct;
use EnginesModel_MMTStruct;
use Exception;
use Exceptions\NotFoundException;
use Exceptions\ValidationError;
use FeatureSet;
use INIT;
use Jobs_JobStruct;
use Log;
use NewController;
use TaskRunner\Commons\QueueElement;
use TaskRunner\Exceptions\EndQueueException;
use TaskRunner\Exceptions\ReQueueException;
use TmKeyManagement_MemoryKeyDao;
use TmKeyManagement_MemoryKeyStruct;
use TmKeyManagement_TmKeyManagement;
use Users\MetadataDao;
use Users_UserStruct;

class Mmt extends BaseFeature {

    const FEATURE_CODE = 'mmt';

    protected $forceOnProject = true;

    /**
     * Called in @see Bootstrap::notifyBootCompleted
     */
    public static function bootstrapCompleted() {
        Constants_Engines::setInEnginesList( Constants_Engines::MMT );
    }

    /**
     * Called in @param EnginesModel_EngineStruct $newCreatedDbRowStruct
     *
     * @param Users_UserStruct $userStruct
     *
     * @return null
     * @throws Exception
     * @see engineController::add()
     *
     */
    public static function postEngineCreation( EnginesModel_EngineStruct $newCreatedDbRowStruct, Users_UserStruct $userStruct ) {

        if ( !$newCreatedDbRowStruct instanceof EnginesModel_MMTStruct ) {
            return $newCreatedDbRowStruct;
        }

        /** @var Engines_MMT $newTestCreatedMT */
        try {
            $newTestCreatedMT = Engine::createTempInstance( $newCreatedDbRowStruct );
        } catch ( Exception $exception ) {
            throw new Exception( "MMT license not valid" );
        }

        // Check account
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
            ( new EnginesModel_EngineDAO( Database::obtain() ) )->delete( $newCreatedDbRowStruct );

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
            ( new EnginesModel_EngineDAO( Database::obtain() ) )->delete( $newCreatedDbRowStruct );
            throw $e;
        }

        $UserMetadataDao = new MetadataDao();
        $UserMetadataDao->set( $userStruct->uid, self::FEATURE_CODE, $newCreatedDbRowStruct->id );

        return $newCreatedDbRowStruct;

    }

    /**
     * Called in @param $errorObject
     *
     * @param $class_load
     *
     * @return array
     * @see engineController::add()
     *
     */
    public function engineCreationFailed( $errorObject, $class_load ) {
        if ( $class_load == Constants_Engines::MMT ) {
            return [ 'code' => 403, 'message' => "Creation failed. Only one ModernMT engine is allowed." ];
        }

        return $errorObject;
    }

    /**
     * Called in
     *
     * @param array                  $config
     * @param Engines_AbstractEngine $engine
     * @param Jobs_JobStruct         $jobStruct
     *
     * @return array
     * @throws Exception
     * @see getContributionController::doAction()
     *
     */
    public static function beforeGetContribution( $config, Engines_AbstractEngine $engine, Jobs_JobStruct $jobStruct ) {

        if ( $engine instanceof Engines_MMT ) {

            //get the Owner Keys from the Job
            $tm_keys          = TmKeyManagement_TmKeyManagement::getOwnerKeys( [ $jobStruct->tm_keys ], 'r' );
            $config[ 'keys' ] = array_map( function ( $tm_key ) {
                /**
                 * @var $tm_key TmKeyManagement_MemoryKeyStruct
                 */
                return $tm_key->key;
            }, $tm_keys );

            $jobsMetadataDao = new \Jobs\MetadataDao();
            $contextRs  = $jobsMetadataDao->setCacheTTL( 60 * 60 * 24 * 30 )->getByIdJob( $jobStruct->id, 'mt_context' );
            $mt_context = @array_pop( $contextRs );

            if ( !empty( $mt_context ) ) {
                $config[ 'mt_context' ] = $mt_context->value;
            }

            // mt evaluation => ice_mt
            $mt_evaluation = $jobsMetadataDao->get( $jobStruct->id, $jobStruct->password, 'mt_evaluation', 10 * 60 )->value ?? null;
            if ( $mt_evaluation ) {
                $config[ 'include_score' ] = true;
            }

            $config[ 'job_id' ]     = $jobStruct->id;
            $config[ 'secret_key' ] = self::getG2FallbackSecretKey();
            $config[ 'priority' ]   = 'normal';

        }

        return $config;

    }
    
    public static function getG2FallbackSecretKey() {
        $secret_key       = [ 'secret_key' => null ];
        $config_file_path = realpath( INIT::$ROOT . '/inc/mmt_fallback_key.ini' );
        if ( file_exists( $config_file_path ) ) {
            $secret_key = parse_ini_file( $config_file_path );
        }

        return $secret_key[ 'secret_key' ];
    }

    /**
     * @param $uid
     *
     * @return TmKeyManagement_MemoryKeyStruct[]
     * @throws Exception
     */
    private static function _getKeyringOwnerKeysByUid( $uid ) {

        /*
         * Take the keys of the user
         */
        try {
            $_keyDao = new TmKeyManagement_MemoryKeyDao( Database::obtain() );
            $dh      = new TmKeyManagement_MemoryKeyStruct( [ 'uid' => $uid ] );
            $keyList = $_keyDao->read( $dh );
        } catch ( Exception $e ) {
            $keyList = [];
            Log::doJsonLog( $e->getMessage() );
        }

        return $keyList;
    }

    /**
     * @param Users_UserStruct $LoggedUser
     *
     * @return TmKeyManagement_MemoryKeyStruct[]
     * @throws Exception
     */
    protected static function _getKeyringOwnerKeys( Users_UserStruct $LoggedUser ) {

        return self::_getKeyringOwnerKeysByUid( $LoggedUser->uid );
    }

    /**
     * Called in @param $projectFeatures
     *
     * @param $controller NewController|createProjectController
     *
     * @return array
     * @throws MMTServiceApiException
     * @throws Exception
     * @see createProjectController::__appendFeaturesToProject()
     *      Called in @see NewController::__appendFeaturesToProject()
     *
     */
    public function filterCreateProjectFeatures( $projectFeatures, $controller ) {

        $engine = Engine::getInstance( $controller->postInput[ 'mt_engine' ] );
        if ( $engine instanceof Engines_MMT ) {
            /**
             * @var $availableLangs
             *     <code>
             *     {
             *     "en":["it","zh-TW"],
             *     "de":["en"]
             *     }
             *     </code>
             */
            $availableLangs       = $engine->getAvailableLanguages();
            $target_language_list = explode( ",", $controller->postInput[ 'target_lang' ] );
            $source_language      = $controller->postInput[ 'source_lang' ];

            foreach ( $availableLangs as $source => $availableTargets ) {

                //take only the language code $langCode is passed by reference, change the value from inside the callback
                array_walk( $availableTargets, function ( &$langCode ) {
                    [ $langCode, ] = explode( "-", $langCode );
                } );

                [ $mSourceCode, ] = explode( "-", $source_language );
                if ( $source == $mSourceCode ) {
                    foreach ( $target_language_list as $_matecatTarget ) {
                        [ $mTargetCode, ] = explode( "-", $_matecatTarget );
                        if ( in_array( $mTargetCode, $availableTargets ) ) {
                            $controller->postInput[ 'target_language_mt_engine_id' ][ $_matecatTarget ] = $controller->postInput[ 'mt_engine' ];
                        } else {
                            $controller->postInput[ 'target_language_mt_engine_id' ][ $_matecatTarget ] = 1; // MyMemory
                        }
                    }
                }
            }

            $feature               = new BasicFeatureStruct();
            $feature->feature_code = self::FEATURE_CODE;
            $projectFeatures[]     = $feature;

        }

        return $projectFeatures;

    }

    /**
     * Called in @param $isValid
     *
     * @param $data             (object)[
     *                          'providerName' => '',
     *                          'logged_user'  => Users_UserStruct,
     *                          'engineData'   => []
     *                          ]
     *
     * @return EnginesModel_EngineStruct|bool
     * @throws AuthenticationError
     * @throws NotFoundException
     * @throws ValidationError
     * @throws EndQueueException
     * @throws ReQueueException
     * @see engineController::add()
     *
     */
    public function buildNewEngineStruct( $isValid, $data ) {

        if ( strtolower( Constants_Engines::MMT ) == $data->providerName ) {

            /**
             * @var $featureSet FeatureSet
             */
            $featureSet = $data->featureSet;

            /**
             * @var $logged_user Users_UserStruct
             */
            $logged_user = $data->logged_user;

            /**
             * Create a record of type MMT
             */
            $newEngineStruct = EnginesModel_MMTStruct::getStruct();

            $newEngineStruct->uid                                        = $logged_user->uid;
            $newEngineStruct->type                                       = Constants_Engines::MT;
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
     * Called in @param                  $memoryKeyStructs TmKeyManagement_MemoryKeyStruct[]
     *
     * @param                  $uid              integer
     *
     * @throws Exception
     * @throws MMTServiceApiException
     * @see      \ProjectManager::setPrivateTMKeys()
     * Called in @see \userKeysController::doAction()
     *
     * @internal param Users_UserStruct $userStruct
     */
    public function postTMKeyCreation( $memoryKeyStructs, $uid ) {

        if ( empty( $memoryKeyStructs ) or empty( $uid ) ) {
            return;
        }

        //retrieve OWNER MMT License
        $ownerMmtEngineMetaData = ( new MetadataDao() )->get( $uid, self::FEATURE_CODE ); // engine_id
        if ( !empty( $ownerMmtEngineMetaData ) ) {

            /**
             * @var Engines_MMT $MMTEngine
             */
            $MMTEngine    = Engine::getInstance( $ownerMmtEngineMetaData->value );
            $engineStruct = $MMTEngine->getEngineRecord();

            $extraParams = $engineStruct->getExtraParamsAsArray();
            $preImport   = $extraParams[ 'MMT-preimport' ];

            if ( $preImport === true ) {
                $MMTEngine->connectKeys( $memoryKeyStructs );
            }
        }
    }
}

