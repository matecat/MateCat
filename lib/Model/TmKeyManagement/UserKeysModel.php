<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 19/02/2018
 * Time: 14:50
 */

namespace TmKeyManagement ;

use Database;
use Exception;
use Log;
use TmKeyManagement_ClientTmKeyStruct;
use TmKeyManagement_Filter;
use TmKeyManagement_MemoryKeyDao;
use TmKeyManagement_MemoryKeyStruct;
use TmKeyManagement_TmKeyStruct;
use Users_UserStruct;

class UserKeysModel {

    protected $reverse_lookup_user_personal_keys = [
            'pos' => [], 'elements' => []
    ];

    protected $_user_keys = [ 'totals' => [], 'job_keys' => [] ] ;

    protected $user ;

    protected $userRole ;

    public function __construct( Users_UserStruct $user, $role ) {
        $this->user = $user ;
        $this->userRole = $role ;
    }

    public function getKeys( $jobKeys ) {
        /*
         * Take the keys of the user
         */
        try {
            $_keyDao = new TmKeyManagement_MemoryKeyDao( Database::obtain() );
            $dh      = new TmKeyManagement_MemoryKeyStruct( array( 'uid' => $this->user->uid ) );
            $keyList = $_keyDao->read( $dh );

        } catch ( Exception $e ) {
            $keyList = array();
            Log::doJsonLog( $e->getMessage() );
        }

        $reverse_lookup_user_personal_keys = [ 'pos' => [], 'elements' => [] ];

        /**
         * Set these keys as editable for the client
         *
         * @var $keyList TmKeyManagement_MemoryKeyStruct[]
         */

        foreach ( $keyList as $_j => $key ) {

            /**
             * @var $_client_tm_key TmKeyManagement_TmKeyStruct
             */

            //create a reverse lookup
            $reverse_lookup_user_personal_keys[ 'pos' ][ $_j ]      = $key->tm_key->key;
            $reverse_lookup_user_personal_keys[ 'elements' ][ $_j ] = $key;

            $this->_user_keys[ 'totals' ][ $_j ] = new TmKeyManagement_ClientTmKeyStruct( $key->tm_key );

        }

        /*
         * Now take the JOB keys
         */
        $job_keyList = json_decode( $jobKeys, true );

        /**
         * Start this N^2 cycle from keys of the job,
         * these should be statistically lesser than the keys of the user
         *
         * @var $keyList array
         */
        foreach ( $job_keyList as $jobKey ) {

            $jobKey = new TmKeyManagement_ClientTmKeyStruct( $jobKey );
            $jobKey->complete_format = true;

            if ( !is_null( $this->user->uid ) && count( $reverse_lookup_user_personal_keys[ 'pos' ] ) ) {

                /*
                 * If user has some personal keys, check for the job keys if they are present, and obfuscate
                 * when they are not
                 */
                $_index_position = array_search( $jobKey->key, $reverse_lookup_user_personal_keys[ 'pos' ] );
                if ( $_index_position !== false ) {

                    //I FOUND A KEY IN THE JOB THAT IS PRESENT IN MY KEYRING
                    //i'm owner?? and the key is an owner type key?
                    if ( !$jobKey->owner && $this->userRole != TmKeyManagement_Filter::OWNER ) {
                        $jobKey->r = $jobKey->{TmKeyManagement_Filter::$GRANTS_MAP[ $this->userRole ][ 'r' ]};
                        $jobKey->w = $jobKey->{TmKeyManagement_Filter::$GRANTS_MAP[ $this->userRole ][ 'w' ]};
                        $jobKey    = $jobKey->hideKey( $this->user->uid );
                    } else {
                        if ( $jobKey->owner && $this->userRole != TmKeyManagement_Filter::OWNER ) {
                            // I'm not the job owner, but i know the key because it is in my keyring
                            // so, i can upload and download TMX, but i don't want it to be removed from job
                            // in tm.html relaxed the control to "key.edit" to enable buttons
                            // $jobKey = $jobKey->hideKey( $uid ); // enable editing

                        } else {
                            if ( $jobKey->owner && $this->userRole == TmKeyManagement_Filter::OWNER ) {
                                //do Nothing
                            }
                        }
                    }

                    //copy the is_shared value from the key inside the Keyring into the key coming from job
                    $jobKey->setShared( $reverse_lookup_user_personal_keys[ 'elements' ][ $_index_position ]->tm_key->isShared() );

                    unset( $this->_user_keys[ 'totals' ][ $_index_position ] );

                } else {

                    /*
                     * This is not a key of that user, set right and obfuscate
                     */
                    $jobKey->r = true;
                    $jobKey->w = true;
                    $jobKey    = $jobKey->hideKey( -1 );

                }

                $this->_user_keys[ 'job_keys' ][] = $jobKey;

            } else {
                /*
                 * This user is anonymous or it has no keys in its keyring, obfuscate all
                 */
                $jobKey->r                        = true;
                $jobKey->w                        = true;
                $this->_user_keys[ 'job_keys' ][] = $jobKey->hideKey( -1 );

            }

        }

        //clean unordered keys
        $this->_user_keys[ 'totals' ] = array_values( $this->_user_keys[ 'totals' ] );

        return $this->_user_keys ;
    }


}