<?php

use TMS\TMSService;
use Users\MetadataDao;

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

    private static $allowed_exec = [
            'delete',
            'update',
            'newKey',
            'info',
            'share'
    ];
    /**
     * @var ?string
     */
    private ?string $remove_from;

    public function __construct() {

        parent::__construct();

        //Session Enabled
        $this->identifyUser();
        //Session Disabled

        //define input filters
        $filterArgs = [
                'exec'        => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'key'         => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'remove_from' => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'emails'      => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'description' => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW
                ],
        ];

        //filter input
        $_postInput = filter_var_array( $_REQUEST, $filterArgs );

        //assign variables
        $this->exec        = $_postInput[ 'exec' ];
        $this->key         = trim( $_postInput[ 'key' ] );
        $this->description = $_postInput[ 'description' ];
        $this->mail_list   = $_postInput[ 'emails' ];
        $this->remove_from = $_postInput[ 'remove_from' ];

        //check for eventual errors on the input passed
        $this->result[ 'errors' ] = [];
        if ( empty( $this->key ) ) {
            $this->result[ 'errors' ][] = [
                    'code'    => -2,
                    'message' => "Key missing"
            ];
            $this->result[ 'success' ]  = false;
        }

        // Prevent XSS attack
        // ===========================
        // POC. Try to add this string in the input:
        // <details x=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx:2 open ontoggle="prompt(document.cookie);">
        // in this case, an error MUST be thrown

        if ( ( $_POST[ 'description' ] ?? null ) !== $this->description ) {
            $this->result[ 'errors' ][] = [
                    'code'    => -3,
                    'message' => "Invalid key description"
            ];
            $this->result[ 'success' ]  = false;
        }

        if ( array_search( $this->exec, self::$allowed_exec ) === false ) {
            $this->result[ 'errors' ][] = [
                    'code'    => -5,
                    'message' => "No method $this->exec allowed."
            ];
            $this->result[ 'success' ]  = false;
        }

        //ONLY LOGGED USERS CAN PERFORM ACTIONS ON KEYS, BUT INFO ARE PUBLIC
        if ( !$this->userIsLogged && $this->exec != 'info' ) {
            $this->result[ 'errors' ][] = [
                    'code'    => -1,
                    'message' => "Login is required to perform this action"
            ];
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
        if ( count( $this->result[ 'errors' ] ?? 0 ) ) {
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
                    $this->removeKeyFromEngines( $userMemoryKeys, $this->remove_from );
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
                    $emailList      = Utils::validateEmailList( $this->mail_list );
                    $userMemoryKeys = $mkDao->read( $memoryKeyToUpdate );
                    ( new TmKeyManagement_TmKeyManagement() )->shareKey( $emailList, $userMemoryKeys[ 0 ], $this->user );
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
            $this->result[ 'errors' ][] = [ "code" => $e->getCode(), "message" => $e->getMessage() ];
        }

    }

    /**
     * @param TmKeyManagement_MemoryKeyStruct[] $userMemoryKeys
     *
     * @throws ReflectionException
     */
    protected function _getKeyUsersInfo( array $userMemoryKeys ) {

        $_userStructs = [];
        foreach ( $userMemoryKeys[ 0 ]->tm_key->getInUsers() as $userStruct ) {
            $_userStructs[] = new Users_ClientUserFacade( $userStruct );
        }
        $this->result = [
                'errors'  => [],
                "data"    => $_userStructs,
                "success" => true
        ];

    }

    private function removeKeyFromEngines( TmKeyManagement_MemoryKeyStruct $memoryKey, ?string $enginesListCsv = '' ) {

        $deleteFrom = array_filter( explode( ",", $enginesListCsv ) );

        foreach ( $deleteFrom as $engineName ) {

            try {

                $struct             = EnginesModel_EngineStruct::getStruct();
                $struct->class_load = $engineName;
                $struct->type       = Constants_Engines::MT;
                $engine             = Engine::createTempInstance( $struct );

                if ( $engine->isAdaptiveMT() ) {
                    $ownerMmtEngineMetaData = ( new MetadataDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->get( $this->getUser()->uid, $engine->getEngineRecord()->class_load ); // engine_id
                    if ( !empty( $ownerMmtEngineMetaData ) ) {
                        $engine    = Engine::getInstance( $ownerMmtEngineMetaData->value );
                        $engineKey = $engine->getMemoryIfMine( $memoryKey );
                        if ( $engineKey ) {
                            $engine->deleteMemory( $engineKey );
                        }
                    }
                }

            } catch ( Exception $e ) {
                Log::doJsonLog( $e->getMessage() );
            }

        }

    }

} 