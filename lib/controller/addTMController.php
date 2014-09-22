<?php

include_once INIT::$MODEL_ROOT . "/queries.php";

/**
 *
 * Class addTMController
 * This class has the responsibility to associate a TM to a job and to check
 * whether a tm key is valid or not.<br/>
 *
 * Handled cases:<br/>
 * <ul>
 *  <li>Existing TM        -> tm key provided</li>
 *  <li>Non-existing TM    -> tmx file uploaded</li>
 *  <li>Logged user        -> tm associated to the user and to the job</li>
 *  <li>Non-logged user    -> tm associated to the job</li>
 * </ul>
 * <br/>
 * <b>Error codes and messages:</b><br/>
 * <table>
 *  <tr><th>Conditions</th><th>Code</th><th>Message</th></tr>
 *  <tr>
 *      <td>Job's id_mt_engine != 1</td>
 *      <td>-1</td>
 *      <td>MT Engine is not MyMemory. TMX cannot be added.</td></tr>
 *  <tr>
 *      <td>tm_key not set</td>
 *      <td>-2</td>
 *      <td>Please specify a TM key.</td></tr>
 *  <tr>
 *      <td>job_id not set</td>
 *      <td>-3</td>
 *      <td>Please specify the job id.</td></tr>
 *  <tr>
 *      <td>job_pass not set</td>
 *      <td>-4</td>
 *      <td>Please specify the job password.</td></tr>
 *  <tr>
 *      <td>r specified but is nor 0 or 1</td>
 *      <td>-5</td>
 *      <td>Read grant must be 0 or 1.</td></tr>
 *  <tr>
 *      <td>w specified but is nor 0 or 1</td>
 *      <td>-6</td>
 *      <td>Write grant must be 0 or 1.</td></tr>
 *  <tr>
 *      <td>exec specified but different from "addTM", "newTM", "checkTMKey"</td>
 *      <td>-7</td>
 *      <td>Action not valid.</td></tr>
 *  <tr>
 *      <td>exec = "addTM" and file not provided</td>
 *      <td>-8</td>
 *      <td>Please upload a TMX.</td></tr>
 *  <tr>
 *      <td>Failed to load TM keys json from the DB or invalid json</td>
 *      <td>-10</td>
 *      <td>Could not retrieve TM keys from the database.</td></tr>
 *  <tr>
 *      <td>r and w both set to 0</td>
 *      <td>-11</td>
 *      <td>Please enable at least one grant flag.</td></tr>
 *  <tr>
 *      <td>MyMemory considers the TMX file invalid</td>
 *      <td>-14</td>
 *      <td>Invalid TMX (<b>MY_TMX_KEY</b>)</td></tr>
 *  <tr>
 *      <td>File upload failed or file import in MyMemory failed</td>
 *      <td>-15</td>
 *      <td>Cant't load TMX files right now, try later.</td></tr>
 *  <tr>
 *      <td>Invalid key provided while importing a file in MyMemory</td>
 *      <td>-15</td>
 *      <td>Invalid key provided</td></tr>
 * </table>
 *
 */
class addTMController extends ajaxController {

    /**
     * @var int The job's id
     */
    private $job_id;

    /**
     * @var string The job's password
     */
    private $job_pass;

    /**
     * @var string The name of the uploaded TMX
     */
    private $name;

    /**
     * @var array
     */
    private $job_data;

    /**
     * @var string The key to be associated to the tmx
     */
    private $tm_key;

    /**
     * @var stdClass
     */
    private $file;

    /**
     * @var string
     */
    private $exec;

    /**
     * @var SimpleTMX
     */
    private $tmxServiceWrapper;

    /**
     * @var LocalAPIKeyService
     */
    private $apiKeyService;

    /**
     * @var int Boolean flag for read grant
     */
    private $r_grant;

    /**
     * @var int Boolean flag for write grant
     */
    private $w_grant;

    /**
     * @var bool Boolean to identify if user is logged or not
     */
    private $isLogged = false;

    /**
     * @var string Project owner's email
     */
    private $ownerID = null;

    private static $acceptedActions = array( "newTM", "addTM" );

    const DEFAULT_READ  = true;
    const DEFAULT_WRITE = true;
    const DEFAULT_TM    = true;
    const DEFAULT_GLOS  = true;

    public function __construct() {

        parent::__construct();

        $filterArgs = array(
                'job_id'   => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'job_pass' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'name'     => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'tm_key'   => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'exec'     => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'r'        => array( 'filter' => FILTER_VALIDATE_BOOLEAN, array( 'flags' => FILTER_NULL_ON_FAILURE ) ),
                'w'        => array( 'filter' => FILTER_VALIDATE_BOOLEAN, array( 'flags' => FILTER_NULL_ON_FAILURE ) )
        );

        $postInput = (object)filter_input_array( INPUT_POST, $filterArgs );

        $this->job_id   = (int)$postInput->job_id;
        $this->job_pass = $postInput->job_pass;
        $this->name     = $postInput->name;
        $this->tm_key   = $postInput->tm_key;
        $this->exec     = $postInput->exec;
        $this->r_grant  = $postInput->r;
        $this->w_grant  = $postInput->w;

        $this->file = $this->uploadFile();

        $this->job_data = getJobData( $this->job_id, $this->job_pass );

        //if Engine is not MyMemory, raise an error to the client.
        if ( !$this->job_data[ 'id_tms' ] == 1 ) {
            $this->result[ 'errors' ][ ] = array(
                    "code" => -1, "message" => "MT Engine is not MyMemory. TMX cannot be added."
            );
        }

        if ( !isset( $this->tm_key ) || is_null( $this->tm_key ) || empty( $this->tm_key ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -2, "message" => "Please specify a TM key." );
        }

        if ( is_null( $this->job_id ) || empty( $this->job_id ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -3, "message" => "Please specify the job id." );
        }

        if ( is_null( $this->job_pass ) || empty( $this->job_pass ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -4, "message" => "Please specify the job password." );
        }

        if ( is_null( $this->r_grant ) ) {
            $this->r_grant = self::DEFAULT_READ;
        }

        if ( is_null( $this->w_grant ) ) {
            $this->w_grant = self::DEFAULT_WRITE;
        }

        if ( !$this->r_grant && !$this->w_grant ) {
            $this->result[ 'errors' ][ ] = array(
                    "code" => -11, "message" => "Please enable at least one grant flag."
            );
        }

        if ( empty( $this->exec ) || !in_array( $this->exec, self::$acceptedActions ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -7, "message" => "Action not valid." );
        }

        //get MyMemory service
        $this->tmxServiceWrapper = TMSServiceFactory::getTMXService( 1 );

        //get MyMemory apiKey service
        $this->apiKeyService = TMSServiceFactory::getAPIKeyService();

        //validate the key
        //This piece of code need to be executed every time
        try {
            $keyExists = $this->apiKeyService->checkCorrectKey( $this->tm_key );
        } catch ( Exception $e ) {

            /* PROVIDED KEY IS NOT VALID OR WRONG, $keyExists IS NOT SET */
            Log::doLog( $e->getMessage() );
            Log::doLog( __METHOD__ . " -> TM key is not valid." );

        }

        if ( !isset($keyExists) || $keyExists === false ) {
            $this->result[ 'errors' ][ ] = array( "code" => -9, "message" => "TM key is not valid." );
        }

        //check if there is a file and if its extension is tmx
        if ( $this->exec == "newTM" ) {
            $i = 0;
            foreach ( $this->file as $k => $fileInfo ) {

                if ( pathinfo( $fileInfo->name, PATHINFO_EXTENSION ) !== 'tmx' ) {
                    $this->result[ 'errors' ][ ] = array( "code" => -8, "message" => "Please upload a TMX." );
                }

                $i++;
            }

            if ( $i == 0 ) {
                $this->result[ 'errors' ][ ] = array( "code" => -8, "message" => "Please upload a TMX." );
            }

        }

    }

    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     */
    public function doAction() {

        //check if there was an error in constructor. If so, stop execution.
        if ( !empty( $this->result[ 'errors' ] ) ) {
            $this->result[ 'success' ] = false;

            return false;
        }

        if ( $this->exec == "newTM" ) {

            if ( $this->addTmxInMyMemory() ) {
                //start loop and wait for the files to be imported in MyMemory
                //MyMemory parses more or less 80 segments/sec per TMX
                if ( !$this->checkTmxImportStatus() ) {
                    $this->result[ 'success' ] = false;
                    return;
                }

            }

        }

        $tmKey_structure = TmKeyManagement_TmKeyManagement::getTmKeyStructure();

        //TODO: tm and glos assignments will take the values from ajax parameters
        $tmKey_structure->tm     = true;
        $tmKey_structure->glos   = true;
        $tmKey_structure->owner  = false;
        $tmKey_structure->name   = $this->name;
        $tmKey_structure->key    = $this->tm_key;
        $tmKey_structure->r      = $this->r_grant;
        $tmKey_structure->w      = $this->w_grant;


        try {
            $job_tmKeys = TmKeyManagement_TmKeyManagement::getJobTmKeys( $this->job_data[ 'tm_keys' ], "rw" );
        } catch ( Exception $e ) {
            $this->result[ 'errors' ][ ] = array(
                    "code" => -10, "message" => "Could not retrieve TM keys from the database."
            );
            $this->result[ 'success' ] = false;
            Log::doLog( __METHOD__ . " -> " . $e->getMessage() );

            return;
        }

        if ( $job_tmKeys == null ) {
            $job_tmKeys = array();
        }

        $this->checkLogin();
        if ( $this->isLogged ) {

            if ( $this->job_data[ 'owner' ] == $this->ownerID ) {
                $tmKey_structure->owner = true;
            }
            //TODO: link tm key to the current user
        }

        //link tm key to the job
        $job_tmKeys = self::putTmKey(
                $job_tmKeys,
                $tmKey_structure
        );

        TmKeyManagement_TmKeyManagement::setJobTmKeys( $this->job_id, $this->job_pass, $job_tmKeys );

        $this->result[ 'errors' ] = array();
        $this->result[ 'success' ] = true;

    }

    /**
     * Saves the uploaded file and returns the file info.
     *
     * @return stdClass
     */
    private function uploadFile() {
        try {
            $uploadManager = new Upload();
            $uploadedFiles = $uploadManager->uploadFiles( $_FILES );
        } catch ( Exception $e ) {
            $this->result[ 'errors' ][ ] = array(
                    "code" => -8, "message" => "Cant't upload TMX files right now, try later."
            );
            Log::doLog( $e->getMessage() );
        }

        return $uploadedFiles;
    }

    /**
     * Import TMX file in MyMemory
     * @return bool
     */
    private function addTmxInMyMemory() {
        $fileImportStarted = true;

        //if there are files, add them into MyMemory
        if ( count( $this->file > 0 ) ) {

            foreach ( $this->file as $k => $fileInfo ) {

                $importStatus = $this->tmxServiceWrapper->import(
                        $fileInfo->file_path,
                        $this->tm_key
                );

                //check for errors during the import
                switch ( $importStatus ) {
                    case "400" :
                        $this->result[ 'errors' ][ ] = array(
                                "code" => -15, "message" => "Cant't load TMX files right now, try later"
                        );
                        $fileImportStarted           = false;
                        break;
                    case "403" :
                        $this->result[ 'errors' ][ ] = array( "code" => -15, "message" => "Invalid key provided" );
                        $fileImportStarted           = false;
                        break;
                    default:
                }
            }

            return $fileImportStarted;
        }

        return false;
    }

    /**
     * Check if TMX has been imported in MyMemory
     * @return bool
     */
    private function checkTmxImportStatus() {
        /**
         * todo: refactor this method and the block in
         * ProjectManager::createProject() into one external method
         */

        foreach ( $this->file as $k => $fileInfo ) {
            $loaded = false;
            //wait until current TMX is loaded
            while ( !$loaded ) {
                //now we repeatedly scan the list of loaded TMs
                //this counter is used to get the latest TM in case of duplicates
                $tmx_max_id = 0;

                //check if TM has been loaded
                $allMemories = $this->tmxServiceWrapper->getStatus( $this->tm_key, $fileInfo->name );

                if ( "200" != $allMemories[ 'responseStatus' ] || 0 == count( $allMemories[ 'responseData' ][ 'tm' ] ) ) {
                    //what the hell? No memories although I've just loaded some? Eject!
                    $this->result[ 'errors' ][ ] = array(
                            "code" => -15, "message" => "Cant't load TMX files right now, try later"
                    );

                    return false;
                }

                //scan through memories
                foreach ( $allMemories[ 'responseData' ][ 'tm' ] as $memory ) {
                    //obtain max id
                    $tmx_max_id = max( $tmx_max_id, $memory[ 'id' ] );

                    //if maximum is current, pick it (it means that, among duplicates, it's the latest)
                    if ( $tmx_max_id == $memory[ 'id' ] ) {
                        $current_tm = $memory;
                    }
                }

                switch ( $current_tm[ 'status' ] ) {
                    case "0":
                        //wait for the daemon to process it
                        //THIS IS WRONG BY DESIGN, WE SHOULD NOT ACT AS AN ASYNCH DAEMON WHILE WE ARE IN A SYNCH APACHE PROCESS
                        Log::doLog( "waiting for \"" . $fileInfo->name . "\" to be loaded into MyMemory" );
                        sleep( 3 );
                        break;
                    case "1":
                        //loaded (or error, in any case go ahead)
                        Log::doLog( "\"" . $fileInfo->name . "\" has been loaded into MyMemory" );
                        $loaded = true;
                        break;
                    default:
                        $this->result[ 'errors' ][ ] = array(
                                "code" => -14, "message" => "Invalid TMX (" . $fileInfo->name . ")"
                        );

                        return false;
                        break;
                }
            }
        }

        return true;
    }

    /**
     * Check user logged
     *
     * @return bool
     */
    public function checkLogin() {
        //Warning, sessions enabled, disable them after check, $_SESSION is in read only mode after disable
        parent::sessionStart();
        $this->isLogged = ( isset( $_SESSION[ 'cid' ] ) && !empty( $_SESSION[ 'cid' ] ) );
        $this->ownerID  = ( isset( $_SESSION[ 'cid' ] ) && !empty( $_SESSION[ 'cid' ] ) ? $_SESSION[ 'cid' ] : null );
        parent::disableSessions();

        return $this->isLogged;
    }

    /**
     * This function adds $newTmKey into $tmKey_arr if it does not exist:
     * if there's not an other tm key having the same key.
     *
     * @param $tmKey_arr Array of TmKeyManagement_TmKeyStruct objects
     * @param $newTmKey TmKeyManagement_TmKeyStruct the new TM to be added
     *
     * @return Array The initial array with the new TM key if it does not exist. <br/>
     *              Otherwise, it returns the initial array.
     */
    private static function putTmKey( $tmKey_arr, $newTmKey ) {
        $added = false;

        foreach ( $tmKey_arr as $i => $curr_tm_key ) {
            /**
             * @var $curr_tm_key TmKeyManagement_TmKeyStruct
             */
            if ( $curr_tm_key->key == $newTmKey->key) {
                $tmKey_arr[ $i ] = $newTmKey;
                $added           = true;
            }
        }

        if ( !$added ) {
            array_push( $tmKey_arr, $newTmKey );
        }

        return $tmKey_arr;
    }
}