<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 02/09/14
 * Time: 15.01
 */
include_once INIT::$MODEL_ROOT . "/queries.php";

class TmKeyManagement_TmKeyManagement {

    /**
     * Returns a TmKeyManagement_TmKeyStruct object. <br/>
     * If a proper associative array is passed, it fills the fields
     * with the array values.
     *
     * @param array|null $tmKey_arr An associative array having
     *                              the same keys of a
     *                              TmKeyManagement_TmKeyStruct object
     *
     * @return TmKeyManagement_TmKeyStruct The converted object
     */
    public static function getTmKeyStructure( $tmKey_arr = null ) {
        return new TmKeyManagement_TmKeyStruct( $tmKey_arr );
    }

    /**
     * Returns a TmKeyManagement_ClientTmKeyStruct object. <br/>
     * If a proper associative array is passed, it fills the fields
     * with the array values.
     *
     * @param array|null $tmKey_arr An associative array having
     *                              the same keys of a
     *                              TmKeyManagement_ClientTmKeyStruct object
     *
     * @return TmKeyManagement_ClientTmKeyStruct The converted object
     */
    public static function getClientTmKeyStructure( $tmKey_arr = null ) {
        return new TmKeyManagement_ClientTmKeyStruct( $tmKey_arr );
    }

    /**
     * Converts a string representing a json_encoded array of TmKeyManagement_TmKeyStruct into an array
     * and filters the elements according to the grants passed.
     *
     * @param   $jsonTmKeys  string  A json string representing an array of TmKeyStruct Objects
     * @param   $grant_level string  One of the following strings : "r", "w", "rw"
     * @param   $type        string  One of the following strings : "tm", "glossary", "tm,glossary"
     * @param   $user_role   string  A constant string of one of the following: TmKeyManagement_Filter::ROLE_TRANSLATOR, TmKeyManagement_Filter::ROLE_REVISOR
     * @param   $uid         int     The user ID, used to retrieve the personal keys
     *
     * @return  array|mixed  An array of TmKeyManagement_TmKeyStruct objects
     * @throws  Exception    Throws Exception if :<br/>
     *                   <ul>
     *                      <li>Json string is malformed</li>
     *                      <li>grant_level string is wrong</li>
     *                      <li>if type string is wrong</li>
     *                      <li>if user role string is wrong</li>
     *                  </ul>
     *
     * @see TmKeyManagement_TmKeyStruct
     */
    public static function getJobTmKeys( $jsonTmKeys, $grant_level = 'rw', $type = "tm", $user_role = TmKeyManagement_Filter::ROLE_TRANSLATOR, $uid = null ) {

        $tmKeys = json_decode( $jsonTmKeys, true );

        if ( is_null( $tmKeys ) ) {
            throw new Exception ( __METHOD__ . " -> Invalid JSON " );
        }

        $filter = new TmKeyManagement_Filter( $uid );
        $filter->setGrants( $grant_level )
                ->setTmType( $type );

        switch ( $user_role ) {
            case TmKeyManagement_Filter::ROLE_TRANSLATOR:
                $tmKeys = array_filter( $tmKeys, array( $filter, 'byTranslator' ) );
                break;
            case TmKeyManagement_Filter::ROLE_REVISOR:
                $tmKeys = array_filter( $tmKeys, array( $filter, 'byRevisor' ) );
                break;
            default:
                throw new Exception( "Filter type $user_role not allowed." );
                break;
        }

        $tmKeys = array_values( $tmKeys );
        $tmKeys = array_map( array( 'self', 'getTmKeyStructure' ), $tmKeys );

        return $tmKeys;
    }

    /**
     * @param $id_job   int
     * @param $job_pass string
     * @param $tm_keys  array
     *
     * @return int|null Returns null if all is ok, otherwise it returns the error code of the mysql Query
     */
    public static function setJobTmKeys( $id_job, $job_pass, $tm_keys ) {
        return setJobTmKeys( $id_job, $job_pass, json_encode( $tm_keys ) );
    }

    /**
     * Converts an array of strings representing a json_encoded array
     * of TmKeyManagement_TmKeyStruct objects into the corresponding array.
     *
     * @param $jsonTmKeys_array array An array of strings representing a json_encoded array of TmKeyManagement_TmKeyStruct objects
     *
     * @return array                  An array of TmKeyManagement_TmKeyStruct objects
     * @throws Exception              Throws Exception if the input is not an array or if a string is not a valid json
     * @see TmKeyManagement_TmKeyStruct
     */
    public static function getOwnerKeys( Array $jsonTmKeys_array ) {

        $result_arr = array();

        foreach ( $jsonTmKeys_array as $pos => $tmKey ) {

            $tmKey = json_decode( $tmKey, true );

            if ( is_null( $tmKey ) ) {
                Log::doLog( __METHOD__ . " -> Invalid JSON." );
                Log::doLog( var_export( $tmKey, true ) );
                throw new Exception ( "Invalid JSON", -2 );
            }

            $filter = new TmKeyManagement_Filter();
            $tmKey  = array_filter( $tmKey, array( $filter, 'byOwner' ) );

            $result_arr[ ] = $tmKey;

        }

        /**
         *
         * Note: Take the shortest array of keys, it's like an intersection between owner keys
         */
        asort( $result_arr );

        //take only the first Job entries
        $result_arr = array_shift( $result_arr );

        //convert tm keys into TmKeyManagement_TmKeyStruct objects
        $result_arr = array_map( array( 'self', 'getTmKeyStructure' ), $result_arr );

        return $result_arr;
    }

    /**
     * Checks if a given array has the same structure of a TmKeyManagement_TmKeyStruct object
     *
     * @param $arr array The array whose structure has to be tested
     *
     * @return TmKeyManagement_TmKeyStruct|bool True if the structure is compliant to a TmKeyManagement_TmKeyStruct object. False otherwise.
     */
    public static function isValidStructure( $arr ) {
        try {
            $myObj = new TmKeyManagement_TmKeyStruct( $arr );
        } catch ( Exception $e ) {
            return false;
        }

        return $myObj;
    }

    /**
     * Converts an array of strings representing a json_encoded array
     * of TmKeyManagement_TmKeyStruct objects into an array of TmKeyManagement_TmKeyStruct objects. <br/>
     * In case of duplicate keys, it will be returned the key with the highest grants.
     *
     *
     * @param $jsonTmKeys_array Array
     *
     * @return mixed
     * @throws Exception
     *
     * @deprecated
     */
    public static function array2TmKeyStructs( Array $jsonTmKeys_array ) {

        $result_arr_withDuplicates = array();

        //decode json arrays into arrays
        foreach ( $jsonTmKeys_array as $i => $jsonTmKey ) {
            $jsonTmKey = json_decode( $jsonTmKey, true );

            if ( is_null( $jsonTmKey ) || ( count( $jsonTmKey ) && empty( $jsonTmKey ) ) ) {
                Log::doLog( __METHOD__ . " -> Invalid JSON." );
                Log::doLog( var_export( $jsonTmKey, true ) );
                throw new Exception( "Invalid JSON" );
            }
            $result_arr_withDuplicates = array_merge( $result_arr_withDuplicates, $jsonTmKey );
        }

        //convert arrays into TmKeyManagement_TmKeyStruct objects
        $result_arr_withDuplicates = array_map( array( 'self', 'getTmKeyStructure' ), $result_arr_withDuplicates );

        //eliminate duplicates.
        $result = array();
        foreach ( $result_arr_withDuplicates as $tmKey ) {
            /**
             * @var $tmKey TmKeyManagement_TmKeyStruct
             */

            //check if element is present in result array.
            $found = false;
            foreach ( $result as $i => $resElem ) {
                /**
                 * @var $resElem TmKeyManagement_TmKeyStruct
                 */

                //If so, choose element with the most generic type and purpose
                //
                if ( $tmKey->equals( $resElem ) ) {

                    $resElem->tm   = filter_var( $resElem->tm, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
                    $tmKey->tm     = filter_var( $tmKey->tm, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
                    $resElem->glos = filter_var( $resElem->glos, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
                    $tmKey->glos   = filter_var( $tmKey->glos, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

                    $resElem->transl = filter_var( $resElem->transl, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
                    $tmKey->transl   = filter_var( $tmKey->transl, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
                    $resElem->rev    = filter_var( $resElem->rev, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
                    $tmKey->rev      = filter_var( $tmKey->rev, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

                    //override grants
                    $resElem->r = $tmKey->r;
                    $resElem->w = $tmKey->w;

                    //choose element with the most generic type (tm, glos)
                    $resElem->tm   = (bool)$resElem->tm | (bool)$tmKey->tm;
                    $resElem->glos = (bool)$resElem->glos | (bool)$tmKey->glos;

                    //choose element with the most generic purpose (transl, rev)
                    //TODO: change this: these fields doesn't exist anymore
                    $resElem->transl = (bool)$resElem->transl | (bool)$tmKey->transl;
                    $resElem->rev    = (bool)$resElem->rev | (bool)$tmKey->rev;

                    //choose a name instead of null
                    if ( $resElem->name === null ) {
                        $resElem->name = $tmKey->name;
                    }

                    //choose an owner instead of null
                    if ( $resElem->owner === null ) {
                        $resElem->owner = $tmKey->owner;
                    }

                    $result[ $i ] = $resElem;
                    $found        = true;
                }
            }
            if ( !$found ) {
                $result[ ] = $tmKey;
            }
        }

        return $result;
    }

    /**
     * Merge the keys from CLIENT with those from DATABASE ( jobData )
     *
     * @param string $Json_clientKeys A json_encoded array of objects having the following structure:<br />
     *                                <pre>
     *                                array(
     *                                'key'  => &lt;private_tm_key>,
     *                                'name' => &lt;tm_name>,
     *                                'r'    => true,
     *                                'w'    => true
     *                                )
     *                                </pre>
     * @param string $Json_jobKeys    A json_encoded array of TmKeyManagement_TmKeyStruct objects
     * @param string $userRole        One of the following strings: "owner", "translator", "revisor"
     * @param int    $uid
     *
     * @see TmKeyManagement_TmKeyStruct
     *
     * @return array TmKeyManagement_TmKeyStruct[]
     *
     * @throws Exception
     */
    public static function mergeJsonKeys( $Json_clientKeys, $Json_jobKeys, $userRole = TmKeyManagement_Filter::ROLE_TRANSLATOR, $uid = null ) {

        //we put the already present job keys so they can be checked against the client keys when cycle advances
        //( jobs has more elements than the client objects )
        $clientDecodedJson = json_decode( $Json_clientKeys, true );
        Utils::jsonExceptionError();
        $serverDecodedJson = json_decode( $Json_jobKeys, true );
        Utils::jsonExceptionError();

        $client_tm_keys = array_map( array( 'self', 'getTmKeyStructure' ), $clientDecodedJson );
        $job_tm_keys    = array_map( array( 'self', 'getTmKeyStructure' ), $serverDecodedJson );

        $client_not_hashed_keys = array( 'pos' => array(), 'elements' => array() );
        foreach ( $client_tm_keys as $_j => $_client_tm_key ) {

            /**
             * @var $_client_tm_key TmKeyManagement_TmKeyStruct
             */

            //remove hashed keys
//            if ( $_client_tm_key->isAnHashedKey() ) {
//                unset( $client_tm_keys[ $_j ] );
//                continue;
//            }

            //create a reverse lookup
            $client_not_hashed_keys[ 'pos' ][ $_j ]      = $_client_tm_key->key;
            $client_not_hashed_keys[ 'elements' ][ $_j ] = $_client_tm_key;

        }

        //update existing keys
        foreach ( $job_tm_keys as $i => $_job_Key ) {
            /**
             * @var $_job_Key TmKeyManagement_TmKeyStruct
             */

            $_index_position = array_search( $_job_Key->key, $client_not_hashed_keys[ 'pos' ] );
            if ( $_index_position !== false ) { // so, here the key exists in client

                //override the static values
                $_job_Key->tm   = filter_var( $client_not_hashed_keys[ 'elements' ][ $_index_position ]->tm, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
                $_job_Key->glos = filter_var( $client_not_hashed_keys[ 'elements' ][ $_index_position ]->glos, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

                if ( $userRole != TmKeyManagement_Filter::OWNER ) {

                    $_job_Key->{TmKeyManagement_Filter::$GRANTS_MAP[ $userRole ][ 'r' ]} = filter_var( $client_not_hashed_keys[ 'elements' ][ $_index_position ]->r, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
                    $_job_Key->{TmKeyManagement_Filter::$GRANTS_MAP[ $userRole ][ 'w' ]} = filter_var( $client_not_hashed_keys[ 'elements' ][ $_index_position ]->w, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

                } elseif ( $userRole == TmKeyManagement_Filter::ROLE_REVISOR || $userRole == TmKeyManagement_Filter::ROLE_TRANSLATOR ){

                    //override grants
                    $_job_Key->r = filter_var( $client_not_hashed_keys[ 'elements' ][ $_index_position ]->r, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
                    $_job_Key->w = filter_var( $client_not_hashed_keys[ 'elements' ][ $_index_position ]->w, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

                } else{
                    throw new Exception ("Invalid Role Type string.", 1);
                }

                //choose a name instead of null
                if ( $_job_Key->name === null ) {
                    $_job_Key->name = $client_not_hashed_keys[ 'elements' ][ $_index_position ]->name;
                }

                //set as owner if it is but should be already set
//                $_job_Key->owner = ( $userRole == TmKeyManagement_Filter::OWNER );

            } elseif ( array_search( $_job_Key->getHash(), $client_not_hashed_keys[ 'pos' ] ) !== false ){
                //DO NOTHING
                //PASS

            } else{

                //Add to TO BE DELETED the key. Why not check if user is the owner of the key??????
                //Nooooo let the client do it for you!!!! It's more safe you don't???
                unset( $job_tm_keys[$i] );

            }

            //reduce the stack
            unset( $client_not_hashed_keys[ 'pos' ][ $_index_position ] );
            unset( $client_not_hashed_keys[ 'elements' ][ $_index_position ] );

        }

        /*
         * There are some new keys from client? Add them
         */
        if ( !empty( $client_not_hashed_keys[ 'pos' ] ) ) {

            $justCreatedKey = new TmKeyManagement_TmKeyStruct();

            foreach ( $client_not_hashed_keys[ 'elements' ] as $_pos => $newClientKey ) {

                /**
                 * @var $newClientKey TmKeyManagement_TmKeyStruct
                 */

                //set the key value
                $justCreatedKey->key = $newClientKey->key;

                //override the static values
                $justCreatedKey->tm   = filter_var( $newClientKey->tm, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
                $justCreatedKey->glos = filter_var( $newClientKey->glos, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

                if ( $userRole != TmKeyManagement_Filter::OWNER ) {
                    $justCreatedKey->{TmKeyManagement_Filter::$GRANTS_MAP[ $userRole ][ 'r' ]} = filter_var( $newClientKey->r, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
                    $justCreatedKey->{TmKeyManagement_Filter::$GRANTS_MAP[ $userRole ][ 'w' ]} = filter_var( $newClientKey->w, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
                } else {
                    //override grants
                    $justCreatedKey->r = filter_var( $newClientKey->r, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
                    $justCreatedKey->w = filter_var( $newClientKey->w, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
                }

                //set the uid property
                if ( $userRole == TmKeyManagement_Filter::ROLE_TRANSLATOR ) {
                    $justCreatedKey->uid_transl = $uid;
                } elseif ( $userRole == TmKeyManagement_Filter::ROLE_REVISOR ) {
                    $justCreatedKey->uid_rev = $uid;
                }

                //choose a name instead of null
                $justCreatedKey->name = $newClientKey->name;

                //choose an owner instead of null
                $justCreatedKey->owner = ( $userRole == TmKeyManagement_Filter::OWNER );


                //finally append to the job keys!!
                $job_tm_keys[ ] = $justCreatedKey;

                //if user is logged, check for key and try to add to
                try {

                    /*
                     * Take the keys of the user
                     */
                    $_keyDao = new TmKeyManagement_MemoryKeyDao( Database::obtain() );
                    $dh       = new TmKeyManagement_MemoryKeyStruct( array( 'uid' => $uid, 'tm_key' => array( 'key' => $justCreatedKey->key ) ) );

                    $keyList = $_keyDao->read( $dh );

                    if( empty( $keyList ) ){

                        // add the key to a new row struct
                        $dh->uid = $uid;
                        $dh->tm_key = $justCreatedKey;

                        $_keyDao->create( $dh );

                    }

                } catch ( Exception $e ) {

                    $keyList = array();
                    Log::doLog( $e->getMessage() );

                }

            }

        }

        return $job_tm_keys;

    }

    /**
     * This function adds $newTmKey into $tmKey_arr if it does not exist:
     * if there's not an other tm key having the same key.
     *
     * @param $tmKey_arr Array of TmKeyManagement_TmKeyStruct objects
     * @param $newTmKey  TmKeyManagement_TmKeyStruct the new TM to be added
     * @param $user_role string Filter Role
     *
     * @return Array The initial array with the new TM key if it does not exist. <br/>
     *              Otherwise, it returns the initial array.
     */
    public static function putTmKey( Array $tmKey_arr, TmKeyManagement_TmKeyStruct $newTmKey, $user_role = TmKeyManagement_Filter::OWNER ) {

        $added = false;

        foreach ( $tmKey_arr as $i => $curr_tm_key ) {
            /**
             * @var $curr_tm_key TmKeyManagement_TmKeyStruct
             */
            if ( $curr_tm_key->key == $newTmKey->key ) {
                switch ( $user_role ) {

                    case TmKeyManagement_Filter::ROLE_TRANSLATOR:
                        $curr_tm_key->r_transl   = $newTmKey->r_transl;
                        $curr_tm_key->w_transl   = $newTmKey->w_transl;
                        $curr_tm_key->uid_transl = $newTmKey->uid_transl;
                        break;

                    case TmKeyManagement_Filter::ROLE_REVISOR:
                        $curr_tm_key->r_rev   = $newTmKey->r_rev;
                        $curr_tm_key->w_rev   = $newTmKey->w_rev;
                        $curr_tm_key->uid_rev = $newTmKey->uid_rev;
                        break;

                    case TmKeyManagement_Filter::OWNER:
                        $curr_tm_key->owner = $newTmKey->owner;
                        $curr_tm_key->r     = $newTmKey->r;
                        $curr_tm_key->w     = $newTmKey->w;
                        break;

                    case null:
                        break;

                    default:
                        break;
                }
                $tmKey_arr[ $i ] = $curr_tm_key;
                $added           = true;
            }
        }

        if ( !$added ) {
            array_push( $tmKey_arr, $newTmKey );
        }

        return $tmKey_arr;
    }

    /**
     * Removes a tm key from an array of tm keys for a specific user type. <br/>
     * If the tm key is still linked to some other user, the result will be the same input array,
     * except for the tm key wo be removed, whose attributes are properly changed according to the user that wanted to
     * remove it.<br/>
     * If user type is wrong, this function will return the input array.
     *
     * @param array                       $tmKey_arr
     * @param TmKeyManagement_TmKeyStruct $newTmKey
     * @param string                      $user_role
     *
     * @return array
     */
    public static function deleteTmKey( Array $tmKey_arr, TmKeyManagement_TmKeyStruct $newTmKey, $user_role = TmKeyManagement_Filter::OWNER ) {
        $result = array();

        foreach ( $tmKey_arr as $i => $curr_tm_key ) {
            /**
             * @var $curr_tm_key TmKeyManagement_TmKeyStruct
             */
            if ( $curr_tm_key->key == $newTmKey->key ) {
                switch ( $user_role ) {

                    case TmKeyManagement_Filter::ROLE_TRANSLATOR:
                        $curr_tm_key->uid_transl = null;
                        $curr_tm_key->r_transl   = null;
                        $curr_tm_key->w_transl   = null;
                        break;

                    case TmKeyManagement_Filter::ROLE_REVISOR:
                        $curr_tm_key->uid_rev = null;
                        $curr_tm_key->r_rev   = null;
                        $curr_tm_key->w_rev   = null;
                        break;

                    case TmKeyManagement_Filter::OWNER:
                        $curr_tm_key->owner = false;
                        $curr_tm_key->r     = null;
                        $curr_tm_key->w     = null;
                        break;

                    case null:
                        break;

                    default:
                        break;
                }

                //if the key is still linked to someone, add it to the result.
                if ( $curr_tm_key->owner ||
                        !is_null( $curr_tm_key->uid_transl ) ||
                        !is_null( $curr_tm_key->uid_rev )
                ) {
                    $result[ ] = $curr_tm_key;
                }
            } else {
                $result[ ] = $curr_tm_key;
            }
        }

        return $result;
    }

}