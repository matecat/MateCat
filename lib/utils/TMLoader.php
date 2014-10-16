<?php

include_once INIT::$MODEL_ROOT . "/queries.php";

/**
 *
 * Class addTMController
 * This class has the responsibility to load a TM into MyMemory
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
 *      <td>tm_key not set</td>
 *      <td>-2</td>
 *      <td>Please specify a TM key.</td></tr>
 *  <tr>
 *      <td>Provided tm_key is not valid</td>
 *      <td>-9</td>
 *      <td>Please upload a TMX.</td></tr>
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
class TMLoader {

    /**
     * @var string The name of the uploaded TMX
     */
    private $name;

    /**
     * @var string The key to be associated to the tmx
     */
    private $tm_key;

    /**
     * @var
     */
    private $file;

    /**
     * @var stdClass
     */
    private $_file;

    /**
     * @var SimpleTMX
     */
    private $tmxServiceWrapper;

    /**
     * @var LocalAPIKeyService
     */
    private $apiKeyService;

    /**
     *
     * @throws Exception
     */
    public function __construct() {

        //get MyMemory service
        $this->tmxServiceWrapper = TMSServiceFactory::getTMXService( 1 );

        //get MyMemory apiKey service
        $this->apiKeyService = TMSServiceFactory::getAPIKeyService();

    }

    /**
     * Check for key correctness
     *
     * @throws Exception
     */
    public function checkCorrectKey(){

        //validate the key
        //This piece of code need to be executed every time
        try {

            $this->apiKeyService->checkCorrectKey( $this->tm_key );

        } catch ( Exception $e ) {

            /* PROVIDED KEY IS NOT VALID OR WRONG, Key IS NOT SET */
            Log::doLog( $e->getMessage() );
            throw $e;

        }

    }

    /**
     * Saves the uploaded file and returns the file info.
     *
     * @return stdClass
     * @throws Exception
     */
    public function uploadFile() {
        try {
            $uploadManager = new Upload();
            $uploadedFiles = $uploadManager->uploadFiles( $_FILES ); Log::doLog( $_FILES );
        } catch ( Exception $e ) {
            Log::doLog( $e->getMessage() );
            throw new Exception( $e->getMessage(), -8);
        }

        return $this->file = $uploadedFiles;
    }

    /**
     * Import TMX file in MyMemory
     * @return bool
     * @throws Exception
     */
    public function addTmxInMyMemory() {

        $this->checkCorrectKey();

        Log::doLog($this->file);

        //if there are files, add them into MyMemory
        if ( count( $this->file ) > 0 ) {

            foreach ( $this->file as $k => $fileInfo ) {

                $importStatus = $this->tmxServiceWrapper->import(
                        $fileInfo->file_path,
                        $this->tm_key
                );

                //check for errors during the import
                switch ( $importStatus ) {
                    case "400" :
                        throw new Exception( "Cant't load TMX files right now, try later", -15);
                        break;
                    case "403" :
                        throw new Exception( "Invalid key provided", -15);
                        break;
                    default:
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Poll this function to know the status of a TMX upload
     *
     */
    public function tmxUploadStatus() {

        $allMemories              = $this->tmxServiceWrapper ->getStatus( $this->tm_key, $this->name );

        Log::doLog( $allMemories );

        if ( "200" != $allMemories[ 'responseStatus' ] || 0 == count( $allMemories[ 'responseData' ][ 'tm' ] ) ) {
            //what the hell? No memories although I've just loaded some? Eject!
            throw new Exception( "Cant't load TMX files right now, try later", -15);
        }

        $tmx_max_id = 0;
        $current_tm = array();

        //scan through memories
        foreach ( $allMemories[ 'responseData' ][ 'tm' ] as $memory ) {
            //obtain max id
            $tmx_max_id = max( $tmx_max_id, $memory[ 'id' ] );

            //if maximum is current, pick it (it means that, among duplicates, it's the latest)
            if ( $tmx_max_id == $memory[ 'id' ] ) {
                $current_tm = $memory;
            }
        }

        $result = array();

        switch ( $current_tm[ 'status' ] ) {
            case "0":
                //wait for the daemon to process it
                //LOADING
                Log::doLog( "waiting for \"" . $current_tm[ 'file_name' ] . "\" to be loaded into MyMemory" );
                $result[ 'data' ]      = array(
                        "done"  => $current_tm[ "temp_seg_ins" ],
                        "total" => $current_tm[ "num_seg_tot" ],
                );
                $result[ 'completed' ] = false;
                break;
            case "1":
                //loaded (or error, in any case go ahead)
                Log::doLog( "\"" . $current_tm[ 'file_name' ] . "\" has been loaded into MyMemory" );
                $result[ 'data' ]      = array(
                        "done"  => $current_tm[ "temp_seg_ins" ],
                        "total" => $current_tm[ "num_seg_tot" ]
                );
                $result[ 'completed' ] = true;
                break;
            default:
                throw new Exception( "Invalid TMX (\"" . $current_tm[ 'file_name' ] . "\")", -14);
                break;
        }

        return $result;

    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName( $name ) {
        $this->name = $name;

        return $this;
    }

    /**
     * @param stdClass $file
     *
     * @return $this
     */
    public function setFile( $file ) {
        $this->file = $file;

        return $this;
    }

    /**
     * @param string $tm_key
     *
     * @return $this
     */
    public function setTmKey( $tm_key ) {
        $this->tm_key = $tm_key;

        return $this;
    }



}