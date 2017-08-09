<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 04/08/17
 * Time: 16.31
 *
 */

namespace Features;


use Constants_Engines;
use Database;
use Engine;
use Engines_AbstractEngine;
use Engines_MMT;
use Exception;
use Jobs\JobStatsStruct;
use Jobs_JobStruct;
use Log;
use TmKeyManagement_MemoryKeyDao;
use TmKeyManagement_MemoryKeyStruct;
use TmKeyManagement_TmKeyManagement;
use Users\MetadataDao;
use Users_UserDao;
use Users_UserStruct;

class Mmt extends BaseFeature {

    protected $autoActivateOnProject = false;

    /**
     *
     */
    public static function bootstrapCompleted(){
        Constants_Engines::setInEnginesList( Constants_Engines::MMT );
    }

    public static function getAvailableEnginesListForUser( $enginesList, Users_UserStruct $userStruct ) {

        $UserMetadataDao = new MetadataDao();
        $engineEnabled = $UserMetadataDao->setCacheTTL( 60 * 60 *24 * 30 )->get( $userStruct->uid, 'mmt' );

        if( !empty( $engineEnabled ) ){
            unset( $enginesList[ Constants_Engines::MMT ] );
        }

        return $enginesList;
    }

    public static function postEngineCreation( Engines_AbstractEngine $engine, Users_UserStruct $userStruct ){

        if( $engine instanceof Engines_MMT ) {
            Database::obtain()->begin();
            $UserMetadataDao = new MetadataDao();
            $UserMetadataDao->setCacheTTL( 60 * 60 * 24 * 30 )->set( $userStruct->uid, 'mmt', $engine->id );
            Database::obtain()->commit();
            $keyList = self::_getKeyringOwnerKeys( $userStruct );
            $engine->activate( $keyList );
        }

    }

    /**
     * @param                        $config
     * @param Engines_AbstractEngine $engine
     * @param Jobs_JobStruct         $jobStruct
     *
     * @return mixed
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

        }

        return $config;

    }

    protected static function _getKeyringOwnerKeys( Users_UserStruct $LoggedUser ) {

        /*
         * Take the keys of the user
         */
        try {
            $_keyDao = new TmKeyManagement_MemoryKeyDao( Database::obtain() );
            $dh      = new TmKeyManagement_MemoryKeyStruct( [ 'uid' => $LoggedUser->uid ] );
            $keyList = $_keyDao->read( $dh );
        } catch ( Exception $e ) {
            $keyList = array();
            Log::doLog( $e->getMessage() );
        }

        return $keyList;

    }

    public static function fastAnalysisComplete( Array $segments, Array $projectRows ){

        $engine = Engine::getInstance( $projectRows[ 'id_mt_engine' ] );

        if( $engine instanceof Engines_MMT ){

            $source       = $segments[ 0 ][ 'source' ];
            $jobLanguages = [];
            foreach( explode( ',', $segments[ 0 ][ 'target' ] ) as $jid_Lang ){
                list( $jobId, $target ) = explode( ":", $jid_Lang );
                $jobLanguages[ $jobId ] = $source . "|" . $target;
            }

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
                $result = $engine->getContext( $tmpFileObject, array_values( $jobLanguages ) );

                $jMetadataDao = new \Jobs\MetadataDao();
                Database::obtain()->begin();
                foreach( $result as $langPair => $context ){
                    $jMetadataDao->setCacheTTL( 60 * 60 * 24 * 30 )->set( array_search( $langPair, $jobLanguages ), "", 'mt_context', $context );
                }
                Database::obtain()->commit();

            } catch( Exception $e ){
                Log::doLog( $e->getMessage() );
                Log::doLog( $e->getTraceAsString() );
            }

        }


//        if( !empty( $projectRows['id_customer'] ) ){
//            $uStruct = ( new Users_UserDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->getByEmail( $projectRows['id_customer'] );
//            $engineEnabled = ( new MetadataDao() )->setCacheTTL( 60 * 60 *24 * 30 )->get( $uStruct->uid, 'mmt' );
//        }

    }

}

