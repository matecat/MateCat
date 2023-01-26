<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 15/10/14
 * Time: 15.44
 */
class userKeysController extends ajaxController {

    private $key;

    private $description;

    private $exec;

    private $mail_list;

    private static $allowed_exec = array(
            'delete',
            'update',
            'newKey',
            'info',
            'share'
    );

    public function __construct() {

        parent::__construct();

        //Session Enabled
        $this->readLoginInfo();
        //Session Disabled

        //define input filters
        $filterArgs = array(
                'exec'        => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'key'         => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'emails'         => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'description' => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
        );

        //filter input
        $_postInput = filter_var_array( $_REQUEST, $filterArgs );

        //assign variables
        $this->exec        = $_postInput[ 'exec' ];
        $this->key         = trim( $_postInput[ 'key' ] );
        $this->description = $_postInput[ 'description' ];
        $this->mail_list   = $_postInput[ 'emails' ];

        //check for eventual errors on the input passed
        $this->result[ 'errors' ] = array();
        if ( empty( $this->key ) ) {
            $this->result[ 'errors' ][] = array(
                    'code'    => -2,
                    'message' => "Key missing"
            );
            $this->result[ 'success' ]  = false;
        }

        if ( array_search( $this->exec, self::$allowed_exec ) === false ) {
            $this->result[ 'errors' ][] = array(
                    'code'    => -5,
                    'message' => "No method $this->exec allowed."
            );
            $this->result[ 'success' ]  = false;
        }

        //ONLY LOGGED USERS CAN PERFORM ACTIONS ON KEYS, BUT INFO ARE PUBLIC
        if ( !$this->userIsLogged && $this->exec != 'info' ) {
            $this->result[ 'errors' ][] = array(
                    'code'    => -1,
                    'message' => "Login is required to perform this action"
            );
            $this->result[ 'success' ]  = false;
        }

    }

    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return void
     */
    function doAction() {
        //if some error occured, stop execution.
        if ( count( @$this->result[ 'errors' ] ) ) {
            return;
        }

        try {
            $tmService = new TMSService();
            $tmService->setTmKey( $this->key );

            //validate the key
            try {
                $keyExists = $tmService->checkCorrectKey();
            } catch ( Exception $e ) {
                /* PROVIDED KEY IS NOT VALID OR WRONG, $keyExists IS NOT SET */
                Log::doJsonLog( $e->getMessage() );
            }

            if ( !isset( $keyExists ) || $keyExists === false ) {
                Log::doJsonLog( __METHOD__ . " -> TM key is not valid." );
                throw new Exception( "TM key is not valid.", -4 );
            }

            $tmKeyStruct       = new TmKeyManagement_TmKeyStruct();
            $tmKeyStruct->key  = $this->key;
            $tmKeyStruct->name = $this->description;
            $tmKeyStruct->tm   = true;
            $tmKeyStruct->glos = true;


            $mkDao = new TmKeyManagement_MemoryKeyDao( Database::obtain() );

            $memoryKeyToUpdate         = new TmKeyManagement_MemoryKeyStruct();
            $memoryKeyToUpdate->uid    = $this->user->uid;
            $memoryKeyToUpdate->tm_key = $tmKeyStruct;

            switch ( $this->exec ) {
                case 'delete':
                    $userMemoryKeys = $mkDao->disable( $memoryKeyToUpdate );
                    $this->featureSet->run('postUserKeyDelete', $userMemoryKeys->tm_key->key, $this->user->uid );
                    break;
                case 'update':
                    $userMemoryKeys = $mkDao->atomicUpdate( $memoryKeyToUpdate );
                    break;
                case 'newKey':
                    $userMemoryKeys = $mkDao->create( $memoryKeyToUpdate );
                    $this->featureSet->run( 'postTMKeyCreation', [ $userMemoryKeys ], $this->user->uid );
                    break;
                case 'info':
                    $userMemoryKeys = $mkDao->read( $memoryKeyToUpdate );
                    $this->_getKeyUsersInfo( $userMemoryKeys );
                    break;
                case 'share':
                    $emailList = $this->_validateEmails();
                    $userMemoryKeys = $mkDao->read( $memoryKeyToUpdate );
                    $this->_shareKey( $emailList, $userMemoryKeys[0], $mkDao );
                    break;
                default:
                    throw new Exception( "Unexpected Exception", -4 );
            }

            if ( !$userMemoryKeys ) {
                throw new Exception( "This key wasn't found in your keyring.", -3 );
            }

        } catch ( Exception $e ) {
            $this->result[ 'data' ]     = 'KO';
            $this->result[ 'success' ]  = false;
            $this->result[ 'errors' ][] = array( "code" => $e->getCode(), "message" => $e->getMessage() );
        }

    }

    /**
     * @param TmKeyManagement_MemoryKeyStruct[] $userMemoryKeys
     */
    protected function _getKeyUsersInfo( Array $userMemoryKeys ){

        $_userStructs = [];
        foreach( $userMemoryKeys[0]->tm_key->getInUsers() as $userStruct ){
            $_userStructs[] = new Users_ClientUserFacade( $userStruct );
        }
        $this->result = [
                'errors'  => [],
                "data"    => $_userStructs,
                "success" => true
        ];

    }

    /**
     * @return array
     */
    protected function _validateEmails(){

        $aValid = [];
        foreach ( explode( ',', $this->mail_list ) AS $sEmailAddress ) {
            $sEmailAddress = trim( $sEmailAddress );
            if( empty( $sEmailAddress ) ) continue;
            $aValid[ $sEmailAddress ] = filter_var( $sEmailAddress, FILTER_VALIDATE_EMAIL );
        }

        $invalidEmails = array_keys( $aValid, false );

        if( !empty( $invalidEmails ) ){
            throw new InvalidArgumentException( "Not valid e-mail provided: " . implode( ", ", $invalidEmails ), -6 );
        }

        return array_keys( $aValid );

    }

    /**
     * @param array                           $emailList
     * @param TmKeyManagement_MemoryKeyStruct $memoryKeyToUpdate
     * @param TmKeyManagement_MemoryKeyDao    $mkDao
     */
    protected function _shareKey( Array $emailList, TmKeyManagement_MemoryKeyStruct $memoryKeyToUpdate, TmKeyManagement_MemoryKeyDao $mkDao ) {

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
                        $this->user,
                        [
                                $alreadyRegisteredRecipient[ 0 ]->email,
                                $alreadyRegisteredRecipient[ 0 ]->fullName()
                        ],
                        $memoryKeyToUpdate
                );
                $email->send();

            } else {

                $email = new TmKeyManagement_ShareKeyEmail( $this->user, [ $email, "" ], $memoryKeyToUpdate );
                $email->send();

            }

        }

    }

    /**
     * @param TmKeyManagement_MemoryKeyStruct $memoryKeyToUpdate
     * @param TmKeyManagement_MemoryKeyDao    $mkDao
     *
     * @return TmKeyManagement_MemoryKeyStruct
     * @throws PDOException
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