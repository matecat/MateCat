<?php

use TMS\TMSService;

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
            //validate the key
            try {
                $keyExists = $tmService->checkCorrectKey( $this->key );
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
                    $emailList = Utils::validateEmailList($this->mail_list);
                    $userMemoryKeys = $mkDao->read( $memoryKeyToUpdate );
                    (new TmKeyManagement_TmKeyManagement())->shareKey($emailList, $userMemoryKeys[0], $this->user);
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

} 