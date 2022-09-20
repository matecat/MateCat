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
use FeatureSet;
use FilesStorage\AbstractFilesStorage;
use INIT;
use Jobs_JobStruct;
use Klein\Klein;
use Log;
use NewController;
use Projects_MetadataDao;
use Projects_ProjectStruct;
use stdClass;
use TaskRunner\Commons\QueueElement;
use TmKeyManagement_MemoryKeyDao;
use TmKeyManagement_MemoryKeyStruct;
use TmKeyManagement_TmKeyManagement;
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
     * @see engineController::add()
     *
     * Only one MMT engine per user can be registered
     *
     */
    public static function getAvailableEnginesListForUser( $enginesList, Users_UserStruct $userStruct ) {

        $UserMetadataDao = new MetadataDao();
        $engineEnabled   = $UserMetadataDao->setCacheTTL( 60 * 60 * 24 * 30 )->get( $userStruct->uid, self::FEATURE_CODE );

        if ( !empty( $engineEnabled ) ) {
            unset( $enginesList[ Constants_Engines::MMT ] ); // remove the engine from the list of available engines like it was disabled, so it will not be created
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

        //gestire il flag synca tutto o no

        if ( !$newCreatedDbRowStruct instanceof EnginesModel_MMTStruct ) {
            return $newCreatedDbRowStruct;
        }

        $newTestCreatedMT = Engine::getInstance( $newCreatedDbRowStruct->id );

        try {

            /**
             * @var $newTestCreatedMT Engines_MMT
             */
            $me_result = $newTestCreatedMT->checkAccount();
            Log::doJsonLog( $me_result );

            $keyList = self::_getKeyringOwnerKeys( $userStruct );
            if ( !empty( $keyList ) ) {
                $newTestCreatedMT->activate( $keyList );
            }

        } catch ( Exception $e ) {
            ( new EnginesModel_EngineDAO( Database::obtain() ) )->delete( $newCreatedDbRowStruct );
            throw $e;
        }

        // @TODO if I remove user_metadata what happens at row 391?
        $UserMetadataDao = new MetadataDao();
        $UserMetadataDao->setCacheTTL( 60 * 60 * 24 * 30 )->set( $userStruct->uid, self::FEATURE_CODE, $newCreatedDbRowStruct->id );

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
     * @see engineController::disable()
     *
     */
    public static function postEngineDeletion( EnginesModel_EngineStruct $engineStruct ) {

        $UserMetadataDao = new MetadataDao();
        $engineEnabled   = $UserMetadataDao->setCacheTTL( 60 * 60 * 24 * 30 )->get( $engineStruct->uid, self::FEATURE_CODE );

        if ( $engineStruct->class_load == Constants_Engines::MMT ) {

            if ( !empty( $engineEnabled ) && $engineEnabled->value == $engineStruct->id /* redundant */ ) {
                $UserMetadataDao->delete( $engineStruct->uid, self::FEATURE_CODE );
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

            $contextRs              = ( new \Jobs\MetadataDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->getByIdJob( $jobStruct->id, 'mt_context' );
            $mt_context             = @array_pop( $contextRs );
            $config[ 'mt_context' ] = ( !empty( $mt_context ) ? $mt_context->value : "" );
            $config[ 'job_id' ]     = $jobStruct->id;
            $config[ 'secret_key' ] = self::getG2FallbackSecretKey();
            $config[ 'priority' ]   = 'normal';

        }

        return $config;

    }

    public static function analysisBeforeMTGetContribution( $config, Engines_AbstractEngine $engine, QueueElement $queueElement ) {

        if ( $engine instanceof Engines_MMT ) {
            $config[ 'secret_key' ] = self::getG2FallbackSecretKey();
            $config[ 'priority' ]   = 'background';
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
     * @param Users_UserStruct $LoggedUser
     *
     * @return TmKeyManagement_MemoryKeyStruct[]
     */
    protected static function _getKeyringOwnerKeys( Users_UserStruct $LoggedUser ) {

        /*
         * Take the keys of the user
         */
        try {
            $_keyDao = new TmKeyManagement_MemoryKeyDao( Database::obtain() );
            $dh      = new TmKeyManagement_MemoryKeyStruct( [ 'uid' => $LoggedUser->uid ] );
            $keyList = $_keyDao->read( $dh );
        } catch ( Exception $e ) {
            $keyList = [];
            Log::doJsonLog( $e->getMessage() );
        }

        return $keyList;

    }

    /**
     * Called in @param $projectFeatures
     *
     * @param $controller NewController|createProjectController
     *
     * @return array
     * @throws MMTServiceApiException
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

            $found = true;
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
    public static function fastAnalysisComplete( array $segments, array $projectRows ) {

        $engine = Engine::getInstance( $projectRows[ 'id_mt_engine' ] );

        if ( $engine instanceof Engines_MMT ) {

            $source       = $segments[ 0 ][ 'source' ];
            $targets      = [];
            $jobLanguages = [];
            foreach ( explode( ',', $segments[ 0 ][ 'target' ] ) as $jid_Lang ) {
                list( $jobId, $target ) = explode( ":", $jid_Lang );
                $jobLanguages[ $jobId ] = $source . "|" . $target;
                $targets[]              = $target;
            }

            $tmp_name      = tempnam( sys_get_temp_dir(), 'mmt_cont_req-' );
            $tmpFileObject = new \SplFileObject( tempnam( sys_get_temp_dir(), 'mmt_cont_req-' ), 'w+' );
            foreach ( $segments as $pos => $segment ) {
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
            }

            unset( $tmpFileObject );
            @unlink( $tmp_name );

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
     * @param stdClass $file
     * @param          $user
     * @param          $tm_key
     *
     * Called in @see \ProjectManager::_pushTMXToMyMemory()
     * Called in @see \loadTMXController::doAction()
     *
     */
    public function postPushTMX( stdClass $file, $user, $tm_key ) {

        //chiedo tutte le chiavi di mymemory ( list o get singola chiave ) e se questa key c'è mando la tmx altrimenti no

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
                    $fileName  = AbstractFilesStorage::pathinfo_fix( $file->file_path, PATHINFO_FILENAME );
                    $result    = $MMTEngine->import( $file->file_path, $tm_key, $fileName );

                    if ( $result->responseStatus >= 400 ) {
                        throw new Exception( $result->error->message );
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
     * @see engineController::add()
     *
     */
    public function buildNewEngineStruct( $isValid, $data ) {

        if ( strtolower( Constants_Engines::MMT ) == $data->providerName ) {

            /**
             * @var $logged_user Users_UserStruct
             */
            $logged_user = $data->logged_user;

            /**
             * Create a record of type MMT
             */
            $newEngineStruct = EnginesModel_MMTStruct::getStruct();

            $newEngineStruct->uid                                    = $logged_user->uid;
            $newEngineStruct->type                                   = Constants_Engines::MT;
            $newEngineStruct->extra_parameters[ 'MMT-License' ]      = $data->engineData[ 'secret' ];
            $newEngineStruct->extra_parameters[ 'MMT-pretranslate' ] = $data->engineData[ 'pretranslate' ];

            return $newEngineStruct;
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

        //usare solo se c'è la flag sync

        if ( empty( $memoryKeyStructs ) || empty( $uid ) ) {
            return;
        }

        //retrieve OWNER MMT License
        $ownerMmtEngineMetaData = ( new MetadataDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->get( $uid, self::FEATURE_CODE ); // engine_id
        if ( !empty( $ownerMmtEngineMetaData ) ) {

            /**
             * @var Engines_MMT $MMTEngine
             */
            $MMTEngine = Engine::getInstance( $ownerMmtEngineMetaData->value );
            $MMTEngine->activate( $memoryKeyStructs );

        }


    }

}

