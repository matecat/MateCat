<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 04/08/17
 * Time: 16.31
 *
 */

namespace Features;


use Analysis\Workers\FastAnalysis;
use API\V2\Exceptions\AuthenticationError;
use BasicFeatureStruct;
use Constants_Engines;
use Contribution\ContributionSetStruct;
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
use FilesStorage\AbstractFilesStorage;
use INIT;
use Jobs_JobStruct;
use Klein\Klein;
use Log;
use NewController;
use Projects_MetadataDao;
use Projects_ProjectDao;
use Projects_ProjectStruct;
use SplFileObject;
use TaskRunner\Commons\QueueElement;
use TaskRunner\Exceptions\EndQueueException;
use TaskRunner\Exceptions\ReQueueException;
use TmKeyManagement_MemoryKeyDao;
use TmKeyManagement_MemoryKeyStruct;
use TmKeyManagement_TmKeyManagement;
use TMS\TMSFile;
use Users\MetadataDao;
use Users_UserDao;
use Users_UserStruct;

class Mmt extends BaseFeature {

    const FEATURE_CODE = 'mmt';

    protected $forceOnProject = true;

    public static function loadRoutes( Klein $klein ) {
        route( '/me', 'GET', '\Features\Mmt\Controller\RedirectMeController', 'redirect' );
    }

    /**
     * Called in @see Bootstrap::notifyBootCompleted
     */
    public static function bootstrapCompleted() {
        Constants_Engines::setInEnginesList( Constants_Engines::MMT );
    }

    /**
     * Called in @param                  $enginesList
     *
     * @param Users_UserStruct $userStruct
     *
     * @return mixed
     * @throws Exception
     * @see engineController::add()
     *
     * Only one MMT engine per user can be registered
     *
     */
    public static function getAvailableEnginesListForUser( $enginesList, Users_UserStruct $userStruct ) {

        $UserMetadataDao = new MetadataDao();
        $engineEnabled   = $UserMetadataDao->get( $userStruct->uid, self::FEATURE_CODE );

        if ( !empty( $engineEnabled ) ) {

            $engine = Engine::getInstance($engineEnabled->value);
            $engineRecord = $engine->getEngineRecord();

            if($engineRecord->active == 1){
                unset( $enginesList[ Constants_Engines::MMT ] ); // remove the engine from the list of available engines like it was disabled, so it will not be created
            }
        }

        return $enginesList;
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
        } catch (Exception $exception){
            throw new Exception("MMT license not valid");
        }

        // Check account
        try {
            $checkAccount = $newTestCreatedMT->checkAccount();

            if(!isset($checkAccount['billingPeriod']['planForCatTool'])){
                throw new Exception("MMT license not valid");
            }

            $planForCatTool = $checkAccount['billingPeriod']['planForCatTool'];

            if($planForCatTool === false){
                throw new Exception("The ModernMT license you entered cannot be used inside CAT tools. Please subscribe to a suitable license to start using the ModernMT plugin.");
            }

        } catch ( Exception $e ){
            ( new EnginesModel_EngineDAO( Database::obtain() ) )->delete( $newCreatedDbRowStruct );

            throw new Exception($e->getMessage(), $e->getCode());
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
     * Called in @param EnginesModel_EngineStruct $engineStruct
     * @throws Exception
     * @see engineController::disable()
     */
    public static function postEngineDeletion( EnginesModel_EngineStruct $engineStruct ) {

        $UserMetadataDao = new MetadataDao();
        $engineEnabled   = $UserMetadataDao->setCacheTTL( 60 * 60 * 24 * 30 )->get( $engineStruct->uid, self::FEATURE_CODE );

        if ( $engineStruct->class_load == Constants_Engines::MMT ) {

            if ( !empty( $engineEnabled ) && $engineEnabled->value == $engineStruct->id /* redundant */ ) {
                $UserMetadataDao->delete( $engineStruct->uid, self::FEATURE_CODE ); // delete the engine from user
            }

        }
    }

    /**
     * Called in @param                        $config
     *
     * @param Engines_AbstractEngine $engine
     * @param Jobs_JobStruct         $jobStruct
     *
     * @return mixed
     * @throws Exception
     * @see getContributionController::doAction()
     *
     */
    public static function beforeGetContribution( $config, Engines_AbstractEngine $engine, Jobs_JobStruct $jobStruct ) {

        if ( $engine instanceof Engines_MMT ) {

            //get the Owner Keys from the Job
            $tm_keys          = TmKeyManagement_TmKeyManagement::getOwnerKeys( [ $jobStruct->tm_keys ] );
            $config[ 'keys' ] = array_map( function ( $tm_key ) {
                /**
                 * @var $tm_key TmKeyManagement_MemoryKeyStruct
                 */
                return $tm_key->key;
            }, $tm_keys );

            $contextRs  = ( new \Jobs\MetadataDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->getByIdJob( $jobStruct->id, 'mt_context' );
            $mt_context = @array_pop( $contextRs );

            if ( !empty( $mt_context ) ) {
                $config[ 'mt_context' ] = $mt_context->value;
            }

            $config[ 'job_id' ]     = $jobStruct->id;
            $config[ 'secret_key' ] = self::getG2FallbackSecretKey();
            $config[ 'priority' ]   = 'normal';

        }

        return $config;

    }

    public static function analysisBeforeMTGetContribution( $config, Engines_AbstractEngine $engine, QueueElement $queueElement ) {

        if ( $engine instanceof Engines_MMT ) {

            $contextRs  = ( new \Jobs\MetadataDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->getByIdJob( $queueElement->params->id_job, 'mt_context' );
            $mt_context = @array_pop( $contextRs );

            if ( !empty( $mt_context ) ) {
                $config[ 'mt_context' ] = $mt_context->value;
            }

            $config[ 'secret_key' ] = self::getG2FallbackSecretKey();
            $config[ 'priority' ]   = 'background';
            $config[ 'keys' ]       = @$config[ 'id_user' ];

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
             * <code>
             *  {
             *     "en":["it","zh-TW"],
             *     "de":["en"]
             *  }
             * </code>
             */
            $availableLangs       = $engine->getAvailableLanguages();
            $target_language_list = explode( ",", $controller->postInput[ 'target_lang' ] );
            $source_language      = $controller->postInput[ 'source_lang' ];

            foreach ( $availableLangs as $source => $availableTargets ) {

                //take only the language code $langCode is passed by reference, change the value from inside the callback
                array_walk( $availableTargets, function ( &$langCode ) {
                    list( $langCode, ) = explode( "-", $langCode );
                } );

                list( $mSourceCode, ) = explode( "-", $source_language );
                if ( $source == $mSourceCode ) {
                    foreach ( $target_language_list as $_matecatTarget ) {
                        list( $mTargetCode, ) = explode( "-", $_matecatTarget );
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
     * Called in @param array $segments
     *
     * @param array $projectRows
     *
     * @throws Exception
     *
     * @see FastAnalysis::main()
     *
     */
    public static function beforeSendSegmentsToTheQueue( array $segments, array $projectRows ) {

        $pid    = $projectRows[ 'id' ];
        $engine = Engine::getInstance( $projectRows[ 'id_mt_engine' ] );

        if ( $engine instanceof Engines_MMT ) {

            if ( !empty( $engine->getEngineRecord()->getExtraParamsAsArray()[ 'MMT-context-analyzer' ] ) ) {

                $source       = $segments[ 0 ][ 'source' ];
                $targets      = [];
                $jobLanguages = [];
                foreach ( explode( ',', $segments[ 0 ][ 'target' ] ) as $jid_Lang ) {
                    list( $jobId, $target ) = explode( ":", $jid_Lang );
                    $jobLanguages[ $jobId ] = $source . "|" . $target;
                    $targets[]              = $target;
                }

                $tmp_name      = tempnam( sys_get_temp_dir(), 'mmt_cont_req-' );
                $tmpFileObject = new SplFileObject( tempnam( sys_get_temp_dir(), 'mmt_cont_req-' ), 'w+' );
                foreach ( $segments as $segment ) {
                    $tmpFileObject->fwrite( $segment[ 'segment' ] . "\n" );
                }

                try {

                    /*
                        $result = Array
                        (
                            [en-US|es-ES] => 1:0.14934476,2:0.08131008,3:0.047170084
                            [en-US|it-IT] =>
                        )
                    */
                    $result = $engine->getContext( $tmpFileObject, $source, $targets );

                    $jMetadataDao = new \Jobs\MetadataDao();

                    Database::obtain()->begin();
                    foreach ( $result as $langPair => $context ) {
                        $jMetadataDao->setCacheTTL( 60 * 60 * 24 * 30 )->set( array_search( $langPair, $jobLanguages ), "", 'mt_context', $context );
                    }
                    Database::obtain()->commit();

                } catch ( Exception $e ) {
                    Log::doJsonLog( $e->getMessage() );
                    Log::doJsonLog( $e->getTraceAsString() );
                } finally {
                    unset( $tmpFileObject );
                    @unlink( $tmp_name );
                }

            }

            try {

                //
                // ==============================================
                // If the MMT-preimport flag is disabled
                // and user is logged in
                // send user keys on a project basis
                // ==============================================
                //
                $preImportIsDisabled = empty( $engine->getEngineRecord()->getExtraParamsAsArray()[ 'MMT-preimport' ] );
                $userIsLogged        = !empty( $projectRows[ 'id_customer' ] ) && $projectRows[ 'id_customer' ] != 'translated_user';

                $user = null;
                if ( $userIsLogged ) {
                    $user = ( new Users_UserDao )->getByEmail( $projectRows[ 'id_customer' ] );
                }

                if ( $preImportIsDisabled and $userIsLogged ) {

                    // retrieve OWNER MMT License
                    $ownerMmtEngineMetaData = ( new MetadataDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->get( $user->uid, self::FEATURE_CODE );
                    if ( !empty( $ownerMmtEngineMetaData ) ) {

                        // get jobs keys
                        $project = Projects_ProjectDao::findById( $pid );

                        foreach ( $project->getJobs() as $job ) {

                            $memoryKeyStructs = [];
                            $jobKeyList       = TmKeyManagement_TmKeyManagement::getJobTmKeys( $job->tm_keys, 'r', 'tm', $user->uid );

                            foreach ( $jobKeyList as $memKey ) {
                                $memoryKeyStructs[] = new TmKeyManagement_MemoryKeyStruct(
                                        [
                                                'uid'    => $user->uid,
                                                'tm_key' => $memKey
                                        ]
                                );
                            }
                            /**
                             * @var Engines_MMT $MMTEngine
                             */
                            $MMTEngine = Engine::getInstance( $ownerMmtEngineMetaData->value );
                            $MMTEngine->connectKeys( $memoryKeyStructs );
                        }
                    }
                }
            } catch ( Exception $e ) {
                Log::doJsonLog( $e->getMessage() );
                Log::doJsonLog( $e->getTraceAsString() );
            }

        }

    }

    /**
     *
     * Called in @param                        $response
     *
     * @param ContributionSetStruct  $contributionStruct
     * @param Projects_ProjectStruct $projectStruct
     *
     * @return ContributionSetStruct|null
     * @see \setTranslationController::evalSetContribution()
     *
     */
    public function filterSetContributionMT( $response, ContributionSetStruct $contributionStruct, Projects_ProjectStruct $projectStruct ) {

        /**
         * When a project is created, it's features and used plugins are stored in project_metadata,
         * When MMT is disabled at global level, old projects will have this feature enabled in meta_data, but the plugin is not Bootstrapped with the hook @see Mmt::bootstrapCompleted()
         *
         * So, the MMT engine is not in the list of available plugins, check and exclude if the Plugin is not enables at global level
         */
        if ( !array_key_exists( Constants_Engines::MMT, Constants_Engines::getAvailableEnginesList() ) ) {
            return $response;
        }

        //Project is not anonymous
        if ( !$projectStruct->isAnonymous() ) {

            try {

                $features = FeatureSet::splitString( $projectStruct->getMetadataValue( Projects_MetadataDao::FEATURES_KEY ) );

                if ( in_array( self::FEATURE_CODE, $features ) ) {
                    $response = $contributionStruct;
                } else {
                    $response = null;
                }

            } catch ( Exception $e ) {
                //DO Nothing
            }

        }

        return $response;

    }

    /**
     *
     * @param TMSFile $file
     * @param          $user
     *
     * Called in @see \ProjectManager::_pushTMXToMyMemory()
     * Called in @see \loadTMXController::doAction()
     *
     */
    public function postPushTMX( TMSFile $file, $user ) {

        //Project is not anonymous
        if ( !empty( $user ) ) {

            $uStruct = $user;
            if ( !$uStruct instanceof Users_UserStruct ) {
                $uStruct = ( new Users_UserDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->getByEmail( $user );
            }

            //retrieve OWNER MMT License
            $ownerMmtEngineMetaData = ( new MetadataDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->get( $uStruct->uid, self::FEATURE_CODE ); // engine_id
            try {

                if ( !empty( $ownerMmtEngineMetaData ) ) {

                    /**
                     * @var Engines_MMT $MMTEngine
                     */
                    $MMTEngine = Engine::getInstance( $ownerMmtEngineMetaData->value );

                    //
                    // ==============================================
                    // Call MMT only if the tmx is already imported
                    // over an existing key in MMT
                    // ==============================================
                    //

                    $associatedMemories = $MMTEngine->getAllMemories();
                    foreach ( $associatedMemories as $memory ) {

                        if ( 'x_mm-' . trim( $file->getTmKey() ) === $memory[ 'externalId' ] ) {
                            $fileName = AbstractFilesStorage::pathinfo_fix( $file->getFilePath(), PATHINFO_FILENAME );
                            $result   = $MMTEngine->import( $file->getFilePath(), $file->getTmKey(), $fileName );

                            if ( $result->responseStatus >= 400 ) {
                                throw new Exception( $result->error->message );
                            }
                        }
                    }
                }

            } catch ( Exception $e ) {
                Log::doJsonLog( $e->getMessage() );
            }
        }

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
     * @param $key
     * @param $uid
     *
     * @throws Exception
     */
    public function postUserKeyDelete( $key, $uid ) {

        /*
         * Comment for now, we have to decide if user can delete key imported in ModernMT directly from matecat, moreover it should have a choice.
         * Maybe He wants to retain the key and the associated memories in its license.
         */

//        $engineToBeDeleted         = EnginesModel_EngineStruct::getStruct();
//        $engineToBeDeleted->uid    = $uid;
//        $engineToBeDeleted->active = true;
//
//        $engineDAO = new EnginesModel_EngineDAO( Database::obtain() );
//        $result    = $engineDAO->read( $engineToBeDeleted );
//
//        if(empty($result)){
//            return;
//        }
//
//        $mmt = new Engines_MMT($result[0]);
//
//        $mmt->deleteMemory("x_mm-".$key);
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

