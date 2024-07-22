<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 02/09/14
 * Time: 15.01
 */

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
        $tmKeyStruct = new TmKeyManagement_TmKeyStruct( $tmKey_arr );
        $tmKeyStruct->complete_format = true;

        return $tmKeyStruct;
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
     * @return  TmKeyManagement_TmKeyStruct[]  An array of TmKeyManagement_TmKeyStruct objects
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
    public static function getJobTmKeys( $jsonTmKeys, $grant_level = 'rw', $type = "tm", $uid = null, $user_role = TmKeyManagement_Filter::ROLE_TRANSLATOR ) {

        $tmKeys = json_decode( $jsonTmKeys, true );
        Utils::raiseJsonExceptionError();

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
                throw new Exception( "Filter type '$user_role' not allowed." );
                break;
        }

        $tmKeys = array_values( $tmKeys );
        $tmKeys = array_map( array( 'self', 'getTmKeyStructure' ), $tmKeys );

        return $tmKeys;
    }

    /**
     * //TODO
     * @param $id_job   int
     * @param $job_pass string
     * @param $tm_keys  array
     *
     * @return int|null Returns null if all is ok, otherwise it returns the error code of the mysql Query
     * @throws Exception
     */
    public static function setJobTmKeys( $id_job, $job_pass, $tm_keys ) {
        /**
         * The setContribution is async and the jobs metadata are cached.
         * Destroy the cache so the async processes can reload the new key data
         * @see \AsyncTasks\Workers\SetContributionWorker
         * @see \Contribution\ContributionSetStruct
         */
        $jobDao  = new \Jobs_JobDao( Database::obtain() );
        $jStruct = new \Jobs_JobStruct( [ 'id' => $id_job, 'password' => $job_pass ] );
        $jobDao->destroyCache( $jStruct );

        $jStruct->tm_keys = json_encode( $tm_keys );
        return $jobDao->updateStruct( $jStruct, [ 'fields' => [ 'tm_keys' ] ] );

    }

    /**
     * Converts an array of strings representing a json_encoded array
     * of TmKeyManagement_TmKeyStruct objects into the corresponding array.
     *
     * @param $jsonTmKeys_array array An array of strings representing a json_encoded array of TmKeyManagement_TmKeyStruct objects
     *
     * @return TmKeyManagement_TmKeyStruct[] An array of TmKeyManagement_TmKeyStruct objects
     * @throws Exception              Throws Exception if the input is not an array or if a string is not a valid json
     * @see TmKeyManagement_TmKeyStruct
     */
    public static function getOwnerKeys( Array $jsonTmKeys_array, $grant_level = 'rw', $type = "tm" ) {

        $result_arr = array();

        foreach ( $jsonTmKeys_array as $pos => $tmKey ) {

            $tmKey = json_decode( $tmKey, true );

            if ( is_null( $tmKey ) ) {
                Log::doJsonLog( __METHOD__ . " -> Invalid JSON." );
                Log::doJsonLog( var_export( $tmKey, true ) );
                throw new Exception ( "Invalid JSON: " . var_export( $jsonTmKeys_array, true ), -2 );
            }

            $filter = new TmKeyManagement_Filter();
            $filter->setGrants( $grant_level )
                ->setTmType( $type );
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
     * This method sanitize fields received with struct
     *
     * @param TmKeyManagement_TmKeyStruct $obj
     *
     * @return TmKeyManagement_TmKeyStruct
     */
    public static function sanitize( TmKeyManagement_TmKeyStruct $obj ){

        if( !is_null( $obj->tm ) ){
            $obj->tm = true && filter_var( $obj->tm, FILTER_VALIDATE_BOOLEAN, array('flags' => FILTER_NULL_ON_FAILURE) );
        }

        if( !is_null( $obj->glos ) ){
            $obj->glos = true && filter_var( $obj->glos, FILTER_VALIDATE_BOOLEAN, array('flags' => FILTER_NULL_ON_FAILURE) );
        }

        if( !is_null( $obj->owner ) ){
            $obj->owner = true && filter_var( $obj->owner, FILTER_VALIDATE_BOOLEAN, array('flags' => FILTER_NULL_ON_FAILURE) );
        }

        if( !is_null( $obj->uid_transl ) ){
            $obj->uid_transl = filter_var( $obj->uid_transl, FILTER_SANITIZE_NUMBER_INT );
        }

        if( !is_null( $obj->uid_rev ) ){
            $obj->uid_rev = filter_var( $obj->uid_rev, FILTER_SANITIZE_NUMBER_INT );
        }

        if( !is_null( $obj->name ) ){
            $obj->name = filter_var( $obj->name, FILTER_SANITIZE_STRING, array('flags' => FILTER_FLAG_STRIP_LOW ) );
        }

        if( !is_null( $obj->key ) ){
            $obj->key = filter_var( $obj->key, FILTER_SANITIZE_STRING, array('flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ) );
        }

        if( !is_null( $obj->r ) ){
            $obj->r = true && filter_var( $obj->r, FILTER_VALIDATE_BOOLEAN, array('flags' => FILTER_NULL_ON_FAILURE) );
        }

        if( !is_null( $obj->w ) ){
            $obj->w = true && filter_var( $obj->w, FILTER_VALIDATE_BOOLEAN, array('flags' => FILTER_NULL_ON_FAILURE) );
        }

        if( !is_null( $obj->r_transl ) ){
            $obj->r_transl = true && filter_var( $obj->r_transl, FILTER_VALIDATE_BOOLEAN, array('flags' => FILTER_NULL_ON_FAILURE) );
        }

        if( !is_null( $obj->w_transl ) ){
            $obj->w_transl = true && filter_var( $obj->w_transl, FILTER_VALIDATE_BOOLEAN, array('flags' => FILTER_NULL_ON_FAILURE) );
        }

        if( !is_null( $obj->r_rev ) ){
            $obj->r_rev = true && filter_var( $obj->r_rev, FILTER_VALIDATE_BOOLEAN, array('flags' => FILTER_NULL_ON_FAILURE) );
        }

        if( !is_null( $obj->w_rev ) ){
            $obj->w_rev = true && filter_var( $obj->w_rev, FILTER_VALIDATE_BOOLEAN, array('flags' => FILTER_NULL_ON_FAILURE) );
        }

        if( !is_null( $obj->source ) ){
            $obj->source = filter_var( $obj->source, FILTER_SANITIZE_STRING, array('flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ) );
        }

        if( !is_null( $obj->target ) ){
            $obj->target = filter_var( $obj->target, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH );
        }

        return $obj;

    }

    /**
     * Merge the keys from CLIENT with those from DATABASE ( jobData )
     *
     * @param string $Json_clientKeys A json_encoded array of objects having the following structure:<br />
     * <pre>
     * array(
     *    'key'  => &lt;private_tm_key>,
     *    'name' => &lt;tm_name>,
     *    'r'    => true,
     *    'w'    => true
     * )
     * </pre>
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
        Utils::raiseJsonExceptionError();
        $serverDecodedJson = json_decode( $Json_jobKeys, true );
        Utils::raiseJsonExceptionError();

        if( !array_key_exists( $userRole, TmKeyManagement_Filter::$GRANTS_MAP ) ) {
            throw new Exception ( "Invalid Role Type string.", 4 );
        }

        $client_tm_keys = array_map( array( 'self', 'getTmKeyStructure' ), $clientDecodedJson );
        $client_tm_keys = array_map( array( 'self', 'sanitize' ), $client_tm_keys );
        $job_tm_keys    = array_map( array( 'self', 'getTmKeyStructure' ), $serverDecodedJson );

        $server_reorder_position = array( );
        $reverse_lookup_client_json = array( 'pos' => array(), 'elements' => array(), 'unique' => array() );
        foreach ( $client_tm_keys as $_j => $_client_tm_key ) {

            /**
             * @var $_client_tm_key TmKeyManagement_TmKeyStruct
             */

            //create a reverse lookup
            $reverse_lookup_client_json[ 'pos' ][ $_j ]      = $_client_tm_key->key;
            $reverse_lookup_client_json[ 'elements' ][ $_j ] = $_client_tm_key;
            $reverse_lookup_client_json[ 'unique' ][ $_j ]   = $_client_tm_key->getCrypt();

            if( empty( $_client_tm_key->r ) && empty( $_client_tm_key->w ) ){
                throw new Exception( "Please, select Lookup and/or Update to activate your TM in this project", 4 );
            }

            if ( empty( $_client_tm_key->key ) ){
                throw new Exception( "Invalid Key Provided", 5 );
            }

        }

        $uniq_num = count( array_unique( $reverse_lookup_client_json[ 'unique' ] ) );

        if( $uniq_num != count( $reverse_lookup_client_json[ 'pos' ] ) )  throw new Exception( "A key is already present in this project.", 5 );

        //update existing job keys
        foreach ( $job_tm_keys as $i => $_job_Key ) {
            /**
             * @var $_job_Key TmKeyManagement_TmKeyStruct
             */

            $_index_position = array_search( $_job_Key->key, $reverse_lookup_client_json[ 'pos' ] );

            if ( array_search( $_job_Key->getCrypt(), $reverse_lookup_client_json[ 'pos' ] ) !== false ) {
                //DO NOTHING
                //reduce the stack
                $hashPosition = array_search( $_job_Key->getCrypt(), $reverse_lookup_client_json[ 'pos' ] );

                unset( $reverse_lookup_client_json[ 'pos' ][ $hashPosition ] );
                unset( $reverse_lookup_client_json[ 'elements' ][ $hashPosition ] );
                //PASS

                //take the new order
                $server_reorder_position[ $hashPosition ] = $_job_Key;

            } elseif ( $_index_position !== false ) { // so, here the key exists in client

                //this is an anonymous user, and a key exists in job
                if( $uid == null ){

                    //check anonymous user, an anonymous user can not change a not anonymous key
                    if ( $userRole == TmKeyManagement_Filter::ROLE_TRANSLATOR ) {

                        if( $_job_Key->uid_transl != null ) throw new Exception( "Anonymous user can not modify existent keys." , 1 );

                    } elseif ( $userRole == TmKeyManagement_Filter::ROLE_REVISOR ) {

                        if( $_job_Key->uid_rev != null ) throw new Exception( "Anonymous user can not modify existent keys." , 2 );

                    } else {

                        if( $uid == null )  throw new Exception( "Anonymous user can not be OWNER" , 3 );

                    }

                }

                //override the static values
                $_job_ket_element = $reverse_lookup_client_json[ 'elements' ][ $_index_position ];
                $_job_Key->tm   = filter_var( $_job_ket_element->tm, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
                $_job_Key->glos = filter_var( $_job_ket_element->glos, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

                if ( $userRole == TmKeyManagement_Filter::OWNER ) {

                    //override grants
                    $_job_Key->r = filter_var( $_job_ket_element->r, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
                    $_job_Key->w = filter_var( $_job_ket_element->w, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

                } elseif ( $userRole == TmKeyManagement_Filter::ROLE_REVISOR || $userRole == TmKeyManagement_Filter::ROLE_TRANSLATOR ) {

                    //override role specific grants
                    $_job_Key->{TmKeyManagement_Filter::$GRANTS_MAP[ $userRole ][ 'r' ]} = filter_var( $_job_ket_element->r, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
                    $_job_Key->{TmKeyManagement_Filter::$GRANTS_MAP[ $userRole ][ 'w' ]} = filter_var( $_job_ket_element->w, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

                }

                //change name if modified
                if ( $_job_Key->name != $_job_ket_element->name ) {
                    $_job_Key->name = $_job_ket_element->name;
                }

                //set as owner if it is but should be already set
//                $_job_Key->owner = ( $userRole == TmKeyManagement_Filter::OWNER );

                //reduce the stack
                unset( $reverse_lookup_client_json[ 'pos' ][ $_index_position ] );
                unset( $reverse_lookup_client_json[ 'elements' ][ $_index_position ] );

                //take the new order
                $server_reorder_position[ $_index_position ] = $_job_Key;

            } else {

                //the key must be deleted
                if ( $userRole == TmKeyManagement_Filter::OWNER ) {

                    //override grants
                    $_job_Key->r = null;
                    $_job_Key->w = null;
                    $_job_Key->owner = false;

                } elseif ( ($uid !== null and ($uid == $_job_Key->uid_rev or $uid == $_job_Key->uid_transl)) and ($userRole == TmKeyManagement_Filter::ROLE_REVISOR || $userRole == TmKeyManagement_Filter::ROLE_TRANSLATOR) ) {

                    //override role specific grants
                    $_job_Key->{TmKeyManagement_Filter::$GRANTS_MAP[ $userRole ][ 'r' ]} = null;
                    $_job_Key->{TmKeyManagement_Filter::$GRANTS_MAP[ $userRole ][ 'w' ]} = null;
                }

                //if the key is no more linked to someone, don't add to the resultset, else reorder if it is not an owner key.
                if ( $_job_Key->owner || !is_null( $_job_Key->uid_transl ) || !is_null( $_job_Key->uid_rev ) ) {

                    if ( !$_job_Key->owner ){

                        //take the new order, put the deleted key at the end of the array
                        //a position VERY LOW ( 1 Million )
                        $server_reorder_position[ 1000000 + $i ] = $_job_Key;

                    } else {

                        if( $userRole != TmKeyManagement_Filter::OWNER ) {
                            //place on top of the owner keys, preserve the order of owner keys by adding it's normal index position
                            $server_reorder_position[ -1000000 + $i ] = $_job_Key;
                        } else {
                            // Remove the key!!!
                            //only the owner can remove its keys
                        }

                    }

                }

            }

        }

        /*
         * There are some new keys from client? Add them
         */
        if ( !empty( $reverse_lookup_client_json[ 'pos' ] ) ) {

            $justCreatedKey = new TmKeyManagement_TmKeyStruct();

            foreach ( $reverse_lookup_client_json[ 'elements' ] as $_pos => $newClientKey ) {

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
                //take the new order, put the new key at the end of the array
                //a position VERY LOW, but BEFORE the deleted keys, so it goes not to the end ( 100 hundred thousand )
                $server_reorder_position[ 100000 + $_pos ] = $justCreatedKey;

                if ( $uid != null ) {

                    //if uid is provided, check for key and try to add to it's memory key ring
                    try {

                        /*
                         * Take the keys of the user
                         */
                        $_keyDao = new TmKeyManagement_MemoryKeyDao( Database::obtain() );
                        $dh      = new TmKeyManagement_MemoryKeyStruct( array(
                            'uid'    => $uid,
                            'tm_key' => new TmKeyManagement_TmKeyStruct( array(
                                'key' => $justCreatedKey->key
                            ) )
                        ) );

                        $keyList = $_keyDao->read( $dh );

                        if ( empty( $keyList ) ) {

                            // add the key to a new row struct
                            $dh->uid    = $uid;
                            $dh->tm_key = $justCreatedKey;

                            $_keyDao->create( $dh );

                        }

                    } catch ( Exception $e ) {
                        Log::doJsonLog( $e->getMessage() );
                    }

                }

            }

        }

        ksort( $server_reorder_position, SORT_NUMERIC );

        $merged_tm_keys = [];

        foreach ( $server_reorder_position as $tm_key){
            if(!self::excludeJobKeyFromMerge($tm_key, $uid)){
                $merged_tm_keys[] = $tm_key;
            }
        }

        return $merged_tm_keys;
    }

    /**
     * Exclude keys if r/w are null (which will be deleted)
     *
     * @param TmKeyManagement_TmKeyStruct $_job_Key
     * @param $uid
     * @return bool
     */
    private static function excludeJobKeyFromMerge(TmKeyManagement_TmKeyStruct $_job_Key, $uid = null)
    {
        if($uid === null){
            return false;
        }

        if($_job_Key->owner){
            return false;
        }

        if( $_job_Key->uid_transl == $uid ){
            if( $_job_Key->w_transl === null and $_job_Key->r_transl === null ){
                return true;
            }

        }

        if( $_job_Key->uid_rev == $uid ) {
            if( $_job_Key->w_rev === null and $_job_Key->r_rev === null ){
                return true;
            }
        }

        return false;
    }

    /**
     * @param TmKeyManagement_TmKeyStruct[] $tm_keys
     * @param $userEmail
     * @param $jobOwnerEmail
     * @return TmKeyManagement_TmKeyStruct[]
     */
    public static function filterOutByOwnership(Array $tm_keys, $userEmail, $jobOwnerEmail)
    {

        foreach ($tm_keys as $k => $tm_key) {

            if ($userEmail != $jobOwnerEmail && $tm_key->owner) {
                unset($tm_keys[$k]);
            } elseif ($userEmail == $jobOwnerEmail && !$tm_key->owner) {
                unset($tm_keys[$k]);
            }

        }

        return $tm_keys;

    }

    /**
     * @param array $emailList
     * @param TmKeyManagement_MemoryKeyStruct $memoryKeyToUpdate
     * @param Users_UserStruct $user
     * @throws Exception
     */
    public function shareKey( Array $emailList, TmKeyManagement_MemoryKeyStruct $memoryKeyToUpdate, Users_UserStruct $user ) {

        $mkDao = new TmKeyManagement_MemoryKeyDao();
        $userDao = new Users_UserDao();

        foreach ( $emailList as $pos => $email ) {

            $userQuery                  = Users_UserStruct::getStruct();
            $userQuery->email           = $email;
            $alreadyRegisteredRecipient = $userDao->setCacheTTL( 60 * 10 )->read( $userQuery );

            if ( !empty( $alreadyRegisteredRecipient ) ) {

                // do not send the email to myself
                if ( $memoryKeyToUpdate->uid == $alreadyRegisteredRecipient[ 0 ]->uid ) {
                    continue;
                }

                $memoryKeyToUpdate->uid = $alreadyRegisteredRecipient[ 0 ]->uid;
                $this->_addToUserKeyRing( $memoryKeyToUpdate, $mkDao );

                /**
                 * @var Users_UserStruct[] $alreadyRegisteredRecipient
                 */
                $email = new TmKeyManagement_ShareKeyEmail(
                    $user,
                    [
                        $alreadyRegisteredRecipient[ 0 ]->email,
                        $alreadyRegisteredRecipient[ 0 ]->fullName()
                    ],
                    $memoryKeyToUpdate
                );
                $email->send();

            } else {

                $email = new TmKeyManagement_ShareKeyEmail( $user, [ $email, "" ], $memoryKeyToUpdate );
                $email->send();

            }

        }

    }

    /**
     * @param TmKeyManagement_MemoryKeyStruct $memoryKeyToUpdate
     * @param TmKeyManagement_MemoryKeyDao $mkDao
     * @return DataAccess_IDaoStruct|TmKeyManagement_MemoryKeyStruct|null
     * @throws Exception
     */
    protected function _addToUserKeyRing( TmKeyManagement_MemoryKeyStruct $memoryKeyToUpdate, TmKeyManagement_MemoryKeyDao $mkDao ){

        try {
            $userMemoryKeys = $mkDao->create( $memoryKeyToUpdate );
        } catch ( PDOException $e ) {
            //if a constraint violation is raised, it's fine, the key is already in the user keyring
            //else raise an exception
            //SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1-xxxxxxxxxxx' for key 'PRIMARY'
            if ( $e->getCode() == 23000 ) {
                //Ensure the key is enabled on the client KeyRing
                $userMemoryKeys = $mkDao->enable( $memoryKeyToUpdate );
            } else {
                throw $e;
            }

        }

        return $userMemoryKeys;

    }
}