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

            $config[ 'mt_context' ] = @array_pop( ( new \Jobs\MetadataDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->getByIdJob( $jobStruct->id, 'mt_context' ) );

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

}