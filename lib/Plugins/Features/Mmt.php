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
use Contribution\Set;
use Database;
use Engine;
use Engines_AbstractEngine;
use Engines_MMT;
use Engines_MyMemory;
use EnginesModel_EngineDAO;
use EnginesModel_EngineStruct;
use EnginesModel_MMTStruct;
use Exception;
use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;
use Jobs_JobStruct;
use Klein\Klein;
use Log;
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

    protected $forceOnProject = true ;

    public static function loadRoutes(  Klein $klein  ){
        route( '/me', 'GET', '\Features\Mmt\Controller\RedirectMeController', 'redirect' );
    }

    /**
     * Called in @see Bootstrap::notifyBootCompleted
     */
    public static function bootstrapCompleted(){
        Constants_Engines::setInEnginesList( Constants_Engines::MMT );
    }

    /**
     * Called in @see engineController::add()
     *
     * Only one MMT engine per user can be registered
     *
     * @param                  $enginesList
     * @param Users_UserStruct $userStruct
     *
     * @return mixed
     */
    public static function getAvailableEnginesListForUser( $enginesList, Users_UserStruct $userStruct ) {

        $UserMetadataDao = new MetadataDao();
        $engineEnabled = $UserMetadataDao->setCacheTTL( 60 * 60 *24 * 30 )->get( $userStruct->uid, 'mmt' );

        if( !empty( $engineEnabled ) ){
            unset( $enginesList[ Constants_Engines::MMT ] ); // remove the engine from the list of available engines like it was disabled, so it will not be created
        }

        return $enginesList;
    }

    /**
     * Called in @see engineController::add()
     *
     * @param EnginesModel_EngineStruct $newCreatedDbRowStruct
     * @param Users_UserStruct          $userStruct
     *
     * @return null
     * @throws Exception
     */
    public static function postEngineCreation( EnginesModel_EngineStruct $newCreatedDbRowStruct, Users_UserStruct $userStruct ) {

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
            if( !empty( $keyList ) ){
                $newTestCreatedMT->activate( $keyList );
            }

        } catch ( Exception $e ) {
            ( new EnginesModel_EngineDAO( Database::obtain() ) )->delete( $newCreatedDbRowStruct );
            throw $e;
        }

        $UserMetadataDao = new MetadataDao();
        $UserMetadataDao->setCacheTTL( 60 * 60 * 24 * 30 )->set( $userStruct->uid, 'mmt', $newCreatedDbRowStruct->id );

        return $newCreatedDbRowStruct;

    }

    /**
     * Called in @see engineController::add()
     *
     * @param $errorObject
     * @param $class_load
     *
     * @return array
     */
    public function engineCreationFailed( $errorObject, $class_load ){
        if( $class_load == Constants_Engines::MMT ){
            return [ 'code' => 403, 'message' => "Creation failed. Only one ModernMT engine is allowed." ];
        }
        return $errorObject;
    }

    /**
     * Called in @see engineController::disable()
     *
     * @param EnginesModel_EngineStruct $engineStruct
     */
    public static function postEngineDeletion( EnginesModel_EngineStruct $engineStruct ){

        $UserMetadataDao = new MetadataDao();
        $engineEnabled = $UserMetadataDao->setCacheTTL( 60 * 60 *24 * 30 )->get( $engineStruct->uid, 'mmt' );

        if( $engineStruct->class_load == Constants_Engines::MMT ){

            if( !empty( $engineEnabled ) && $engineEnabled->value == $engineStruct->id /* redundant */ ){
                $UserMetadataDao->delete( $engineStruct->uid, 'mmt' );
            }

        }

    }

    /**
     * Called in @see getContributionController::doAction()
     *
     * @param                        $config
     * @param Engines_AbstractEngine $engine
     * @param Jobs_JobStruct         $jobStruct
     *
     * @return mixed
     * @throws Exception
     */
    public static function beforeGetContribution( $config, Engines_AbstractEngine $engine, Jobs_JobStruct $jobStruct ){

        if( $engine instanceof Engines_MMT ){

            //get the Owner keys Keys from the Job
            $tm_keys = TmKeyManagement_TmKeyManagement::getOwnerKeys( [ $jobStruct->tm_keys ] );
            $config[ 'keys' ] = array_map( function( $tm_key ) {
                /**
                 * @var $tm_key TmKeyManagement_MemoryKeyStruct
                 */
                return $tm_key->key;
            }, $tm_keys );

            $mt_context = @array_pop( ( new \Jobs\MetadataDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->getByIdJob( $jobStruct->id, 'mt_context' ) );
            $config[ 'mt_context' ] = ( !empty( $mt_context ) ? $mt_context->value : "" );
            $config[ 'job_id' ] = $jobStruct->id;
            $config[ 'secret_key' ] = self::getSecretKey();

        }

        return $config;

    }

    public static function analysisBeforeMTGetContribution( $config, Engines_AbstractEngine $engine, QueueElement $queueElement ){

        if( $engine instanceof Engines_MMT ){
            $config[ 'secret_key' ] = self::getSecretKey();
        }

        return $config;

    }

    public static function getSecretKey() {
        $secret_key = [ 'secret_key' => null ];
        $config_file_path = realpath( \INIT::$ROOT . '/inc/mmt_fallback_key.ini' );
        if( file_exists( $config_file_path ) ){
            $secret_key = parse_ini_file( $config_file_path ) ;
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
     * Called in @see createProjectController::__appendFeaturesToProject()
     * Called in @see NewController::__appendFeaturesToProject()
     *
     * @param $projectFeatures
     * @param $controller \NewController|\createProjectController
     *
     * @return array
     * @throws \Engines\MMT\MMTServiceApiException
     */
    public function filterCreateProjectFeatures( $projectFeatures, $controller ){

        $engine = Engine::getInstance( $controller->postInput[ 'mt_engine' ] );
        if( $engine instanceof Engines_MMT ){
            /**
             * @var $availableLangs
             * <code>
             *  {
             *     "en":["it","zh-TW"],
             *     "de":["en"]
             *  }
             * </code>
             */
            $availableLangs = $engine->getAvailableLanguages();
            $target_language_list = explode( ",", $controller->postInput[ 'target_lang' ] );
            $source_language = $controller->postInput[ 'source_lang' ];

            $found = true;
            foreach( $availableLangs as $source => $availableTargets ){

                //take only the language code $langCode is passed by reference, change the value from inside the callback
                array_walk( $availableTargets,function( &$langCode ){
                    list( $langCode, ) = explode( "-", $langCode );
                } );

                list( $mSourceCode, ) = explode( "-", $source_language );
                if( $source == $mSourceCode ){
                    foreach( $target_language_list as $_matecatTarget ){
                        list( $mTargetCode, ) = explode( "-", $_matecatTarget );
                        if( in_array( $mTargetCode, $availableTargets ) ){
                            $controller->postInput[ 'target_language_mt_engine_id' ][ $_matecatTarget ] = $controller->postInput[ 'mt_engine' ];
                        } else {
                            $controller->postInput[ 'target_language_mt_engine_id' ][ $_matecatTarget ] = 1; // MyMemory
                        }
                    }
                }
            }

            $feature                 = new BasicFeatureStruct();
            $feature->feature_code   = self::FEATURE_CODE;
            $projectFeatures[] = $feature;

        }

        return $projectFeatures;

    }

    /**
     * Called in @see FastAnalysis::main()
     *
     * @param array $segments
     * @param array $projectRows
     *
     * @throws Exception
     *
     */
    public static function fastAnalysisComplete( Array $segments, Array $projectRows ){

        $engine = Engine::getInstance( $projectRows[ 'id_mt_engine' ] );

        if( $engine instanceof Engines_MMT ){

            $source       = $segments[ 0 ][ 'source' ];
            $targets = [];
            $jobLanguages = [];
            foreach( explode( ',', $segments[ 0 ][ 'target' ] ) as $jid_Lang ){
                list( $jobId, $target ) = explode( ":", $jid_Lang );
                $jobLanguages[ $jobId ] = $source . "|" . $target;
                $targets[] = $target;
            }

            $tmp_name = tempnam( sys_get_temp_dir(), 'mmt_cont_req-' );
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
                foreach( $result as $langPair => $context ){
                    $jMetadataDao->setCacheTTL( 60 * 60 * 24 * 30 )->set( array_search( $langPair, $jobLanguages ), "", 'mt_context', $context );
                }
                Database::obtain()->commit();

            } catch( Exception $e ){
                Log::doJsonLog( $e->getMessage() );
                Log::doJsonLog( $e->getTraceAsString() );
            }

            unset( $tmpFileObject );
            @unlink( $tmp_name );

        }

    }

    /**
     *
     * Called in @see \setTranslationController::evalSetContribution()
     *
     * @param                        $response
     * @param ContributionSetStruct  $contributionStruct
     * @param Projects_ProjectStruct $projectStruct
     *
     * @return ContributionSetStruct|null
     */
    public function filterSetContributionMT( $response, ContributionSetStruct $contributionStruct, Projects_ProjectStruct $projectStruct ){

        /**
         * When a project is created, it's features and used plugins are stored in project_metadata,
         * When MMT is disabled at global level, old projects will have this feature enabled in meta_data, but the plugin is not Bootstrapped with the hook @see Mmt::bootstrapCompleted()
         *
         * So, the MMT engine is not in the list of available plugins, check and exclude if the Plugin is not enables at global level
         */
        if( !array_key_exists( Constants_Engines::MMT, Constants_Engines::getAvailableEnginesList() ) ) return $response;

        //Project is not anonymous
        if( !empty( $projectStruct->id_customer ) ){

            //retrieve OWNER MMT License
            $uStruct        = ( new Users_UserDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->getByEmail( $projectStruct->id_customer );
            $ownerMmtEngineMetaData = ( new MetadataDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->get( $uStruct->uid, 'mmt' ); // engine_id

            try {

                if( !empty( $ownerMmtEngineMetaData ) ){

                    if( $contributionStruct->id_mt == $ownerMmtEngineMetaData->value ){

                        //Execute the normal Set::setContributionMT called from setTranslation controller
                        $response = $contributionStruct;

                    } else {

                        $mmtContribution = clone $contributionStruct;
                        //Override the mt_engine id and send the message to MMT also
                        $mmtContribution->id_mt = $ownerMmtEngineMetaData->value;
                        //send two contribution, one for the job engine and one for user MMT through the normal Set::contributionMT
                        Set::contributionMT( $mmtContribution );

                        $job_MtEngine = Engine::getInstance( $contributionStruct->id_mt );
                        if( $job_MtEngine instanceof Engines_MyMemory ){
                            $response = null;
                        } else{
                            $response = $contributionStruct;
                        }

                    }

                }

            } catch ( Exception $e ) {
                //DO Nothing
            }

        }

        return $response;

    }

    /**
     * Called in @see \ProjectManager::_pushTMXToMyMemory()
     * Called in @see \loadTMXController::doAction()
     *
     * @param stdClass $file
     * @param          $user
     * @param          $tm_key
     */
    public function postPushTMX( stdClass $file, $user, $tm_key ) {

        //Project is not anonymous
        if ( !empty( $user ) ) {

            $uStruct = $user;
            if ( !$uStruct instanceof Users_UserStruct ) {
                $uStruct = ( new Users_UserDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->getByEmail( $user );
            }

            //retrieve OWNER MMT License
            $ownerMmtEngineMetaData = ( new MetadataDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->get( $uStruct->uid, 'mmt' ); // engine_id
            try {

                if ( !empty( $ownerMmtEngineMetaData ) ) {
                    /**
                     * @var Engines_MMT $MMTEngine
                     */
                    $MMTEngine = Engine::getInstance( $ownerMmtEngineMetaData->value );
                    $fileName = AbstractFilesStorage::pathinfo_fix( $file->file_path, PATHINFO_FILENAME );
                    $result = $MMTEngine->import( $file->file_path, $tm_key, $fileName );

                    if( $result->responseStatus >= 400 ){
                        throw new Exception( $result->error->message );
                    }

                }

            } catch ( Exception $e ){
                Log::doJsonLog( $e->getMessage() );
            }
        }

    }

    /**
     * Called in @see engineController::add()
     *
     * @param $isValid
     * @param $data             (object)[
     *                          'providerName' => '',
     *                          'logged_user'  => Users_UserStruct,
     *                          'engineData'   => []
     *                          ]
     *
     * @return EnginesModel_EngineStruct|bool
     */
    public function buildNewEngineStruct( $isValid, $data ){

        if( strtolower( Constants_Engines::MMT ) == $data->providerName ){

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
            $newEngineStruct->extra_parameters[ 'MMT-pretranslate' ]      = $data->engineData[ 'pretranslate' ];

            return $newEngineStruct;
        }

        return $isValid;

    }

    /**
     * Called in @see \ProjectManager::setPrivateTMKeys()
     * Called in @see \userKeysController::doAction()
     *
     * @param                  $memoryKeyStructs TmKeyManagement_MemoryKeyStruct[]
     * @param                  $uid              integer
     *
     * @throws Exception
     * @throws \Engines\MMT\MMTServiceApiException
     * @internal param Users_UserStruct $userStruct
     */
    public function postTMKeyCreation( $memoryKeyStructs, $uid ){

        if( empty( $memoryKeyStructs ) || empty( $uid ) ){
            return;
        }

        //retrieve OWNER MMT License
        $ownerMmtEngineMetaData = ( new MetadataDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->get( $uid, 'mmt' ); // engine_id
        if ( !empty( $ownerMmtEngineMetaData ) ) {

            /**
             * @var Engines_MMT $MMTEngine
             */
            $MMTEngine = Engine::getInstance( $ownerMmtEngineMetaData->value );
            $MMTEngine->activate( $memoryKeyStructs );

        }


    }

}

