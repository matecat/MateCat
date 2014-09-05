<?php

include_once INIT::$MODEL_ROOT . "/queries.php";

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
    private $action;

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

    private static $acceptedActions = array( "newTM", "addTM" );

    public function __construct() {
        parent::__construct();

        $filterArgs = array(
            'job_id'   => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'job_pass' => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'name'     => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'tm_key'   => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'exec'     => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'r'        => array( 'filter' => FILTER_SANITIZE_STRING),
            'w'        => array( 'filter' => FILTER_SANITIZE_STRING)
        );

        $postInput = (object) filter_input_array( INPUT_POST, $filterArgs );

        $this->job_id   = (int) $postInput->job_id;
        $this->job_pass = $postInput->job_pass;
        $this->name     = $postInput->name;
        $this->tm_key   = $postInput->tm_key;
        $this->exec     = $postInput->exec;
        $this->r_grant  = $postInput->r;
        $this->w_grant  = $postInput->w;

        $this->file = $this->uploadFile();

        $this->job_data = getJobData( $this->job_id, $this->job_pass );

        //if Engine is not MyMemory, raise an error to the client.
        if ( !$this->job_data[ 'id_mt_engine' ] == 1 ) {
            $this->result[ 'errors' ][ ] = array( "code" => -1, "message" => "MT Engine is not MyMemory. TMX cannot be added." );
        }

        if ( empty( $this->tm_key ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -2, "message" => "Please specify a TM key." );
        }

        if ( empty( $this->job_id ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -3, "message" => "Please specify the job id." );
        }

        if ( empty( $this->job_pass ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -4, "message" => "Please specify the job password." );
        }

        if ( $this->r_grant != 0 && empty( $this->r_grant ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -5, "message" => "Please specify the read grant." );
        }

        if ( !is_numeric($this->r_grant)) {
            $this->result[ 'errors' ][ ] = array( "code" => -5, "message" => "Read grant must be 0 or 1." );
        }

        if ( $this->w_grant != 0 && empty( $this->w_grant ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -6, "message" => "Please specify the write grant." );
        }

        if ( !is_numeric($this->w_grant)) {
            $this->result[ 'errors' ][ ] = array( "code" => -6, "message" => "Write grant must be 0 or 1." );
        }

        if ( empty( $this->exec ) || !in_array( $this->exec, self::$acceptedActions ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -7, "message" => "Action not valid." );
        }

        //get MyMemory service
        $this->tmxServiceWrapper = TMSServiceFactory::getTMXService( 1 );

        //get MyMemory apiKey service
        $this->apiKeyService = TMSServiceFactory::getAPIKeyService();

        //check if there is a file and if its extension is tmx
        if ( $this->exec == "newTM" ) {
            $i = 0;
            foreach ( $this->file as $k => $fileInfo ) {

                if ( pathinfo( $fileInfo->name, PATHINFO_EXTENSION ) !== 'tmx' )
                    $this->result[ 'errors' ][ ] = array( "code" => -8, "message" => "Please upload a TMX." );
                $i++;
            }

            if ( $i == 0 )
                $this->result[ 'errors' ][ ] = array( "code" => -8, "message" => "Please upload a TMX." );
        }
    }

    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     */
    public function doAction() {
        //check if there was an error in constructor. If so, stop execution.
        if ( !empty( $this->result[ 'errors' ] ) ) return false;

        //validate the key
        $keyExists = $this->apiKeyService->checkCorrectKey( $this->tm_key );
        if ( !$keyExists ) {
            $this->result[ 'errors' ][ ] = array( "code" => -9, "message" => "TM key is not valid." );

            return;
        }

        if ( $this->exec == "newTM" ) {

            if ( $this->addTmxInMyMemory() ) {
                //start loop and wait for the files to be imported in MyMemory
                //MyMemory parses more or less 80 segments/sec per TMX
                if ( !$this->checkTmxImportStatus() ) return;
            }
        }

        $tmKey_structure = array(
            'type'      => "tmx",
            'owner'     => 0,
            'name'      => $this->name,
            'key'       => $this->tm_key,
            'r'         => $this->r_grant,
            'w'         => $this->w_grant
        );

        try{
            $job_tmKeys = TmKeyManagement_TmKeyManagement::getJobTmKeys($this->job_data[ 'tm_keys' ], "rw");
        }
        catch(Exception $e ){
            $this->result[ 'errors' ][ ] = array( "code" => -10, "message" => "Could not retrieve TM keys from the database." );
            Log::doLog($e->getMessage());
            return;
        }

        if($job_tmKeys == null) $job_tmKeys = array();

        if ( $this->isLoggedIn() ) {

            if($this->job_data['owner'] == $_SESSION[ 'cid' ]){
                $tmKey_structure['owner'] = 1;
            }
            //todo: link tm key to the current user
        }

        //link tm key to the job
        $job_tmKeys = self::putTmKey($job_tmKeys, $tmKey_structure);

        TmKeyManagement_TmKeyManagement::setJobTmKeys($this->job_id, $this->job_pass, $job_tmKeys);
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
            $this->result[ 'errors' ][ ] = array( "code" => -8, "message" => "Cant't upload TMX files right now, try later." );
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
                        $this->result[ 'errors' ][ ] = array( "code" => -15, "message" => "Cant't load TMX files right now, try later" );
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
                    $this->result[ 'errors' ][ ] = array( "code" => -15, "message" => "Cant't load TMX files right now, try later" );

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
                        $this->result[ 'errors' ][ ] = array( "code" => -14, "message" => "Invalid TMX (" . $fileInfo->name . ")" );

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
    public function isLoggedIn() {
        return ( isset( $_SESSION[ 'cid' ] ) && !empty( $_SESSION[ 'cid' ] ) );
    }

    private static function putTmKey($tmKey_arr, $newTmKey){
        $added = false;

        foreach($tmKey_arr as $i => $curr_tm_key){
            if($curr_tm_key['key'] == $newTmKey['key']){
                $tmKey_arr[$i] = $newTmKey;
                $added = true;
            }
        }

        if(!$added) array_push($tmKey_arr, $newTmKey);

        return $tmKey_arr;
    }
}