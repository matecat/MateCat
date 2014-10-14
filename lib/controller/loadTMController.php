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
 *      <td>exec specified but different from  "newTM"</td>
 *      <td>-7</td>
 *      <td>Action not valid.</td></tr>
 *  <tr>
 *      <td>exec = "addTM" and file not provided</td>
 *      <td>-8</td>
 *      <td>Please upload a TMX.</td></tr>
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
class LoadTMController extends ajaxController {

    /**
     * @var string The name of the uploaded TMX
     */
    private $name;

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

    private static $acceptedActions = array( "newTM" );

    const DEFAULT_READ = true;
    const DEFAULT_WRITE = true;
    const DEFAULT_TM = true;
    const DEFAULT_GLOS = true;

    public function __construct() {

        parent::__construct();

        $filterArgs = array(
                'name'   => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'tm_key' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'exec'   => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                )
        );

        $postInput = (object)filter_input_array( INPUT_POST, $filterArgs );

        $this->name     = $postInput->name;
        $this->tm_key   = $postInput->tm_key;
        $this->exec     = $postInput->exec;

        $this->file = $this->uploadFile();

        if ( !isset( $this->tm_key ) || is_null( $this->tm_key ) || empty( $this->tm_key ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -2, "message" => "Please specify a TM key." );
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

        if ( !isset( $keyExists ) || $keyExists === false ) {
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

            $this->addTmxInMyMemory();

        }


        $this->result[ 'errors' ]  = array();
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


}