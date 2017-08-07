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
use Engines_AbstractEngine;
use Engines_MMT;
use TmKeyManagement_MemoryKeyStruct;
use TmKeyManagement_TmKeyManagement;
use Users\MetadataDao;
use Users_UserStruct;

class Mmt extends BaseFeature {

    protected $autoActivateOnProject = false;

    public function bootstrapCompleted(){
        Constants_Engines::setInEnginesList( \Constants_Engines::MMT );
    }

    public function getAvailableEnginesListForUser( $enginesList, Users_UserStruct $userStruct ) {

        $UserMetadataDao = new MetadataDao();
        $engineEnabled = $UserMetadataDao->setCacheTTL( 60 * 60 *24 * 30 )->get( $userStruct->uid, 'mmt' );

        if( !empty( $engineEnabled ) ){
            unset( $enginesList[ \Constants_Engines::MMT ] );
        }

        return $enginesList;
    }

    public function postEngineCreation( Users_UserStruct $userStruct, $engineID ){
        $UserMetadataDao = new MetadataDao();
        $UserMetadataDao->setCacheTTL( 60 * 60 *24 * 30 )->set( $userStruct->uid, 'mmt', $engineID );
    }

    public function beforeGetContribution( $config, Engines_AbstractEngine $mt, $tm_keys_json_string, $id_job ){

        if( $mt instanceof Engines_MMT ){

            $tm_keys = TmKeyManagement_TmKeyManagement::getOwnerKeys( [ $tm_keys_json_string ] );
            $config[ 'keys' ] = array_map( function( $tm_key ) {
                /**
                 * @var $tm_key TmKeyManagement_MemoryKeyStruct
                 */
                return $tm_key->key;
            }, $tm_keys );

            $config[ 'mt_context' ] = @array_pop( ( new \Jobs\MetadataDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->getByIdJob( $id_job, 'mt_context' ) );

        }

        return $config;

    }

}