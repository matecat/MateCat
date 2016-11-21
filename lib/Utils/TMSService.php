<?php

include_once INIT::$MODEL_ROOT . "/queries.php";

class TMSService {

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
     * @var Engines_MyMemory
     */
    private $mymemory_engine;

    private $output_type;

    /**
     *
     * @throws Exception
     */
    public function __construct() {

        //get MyMemory service
        $this->mymemory_engine = Engine::getInstance( 1 );

        $this->output_type = 'translation';

    }

    /**
     * @param string $output_type
     */
    public function setOutputType( $output_type ) {
        $this->output_type = $output_type;
    }

    /**
     * Check for key correctness
     *
     * @throws Exception
     */
    public function checkCorrectKey() {

        $isValid = true;

        //validate the key
        //This piece of code need to be executed every time
        try {

            $isValid = $this->mymemory_engine->checkCorrectKey( $this->tm_key );

        } catch ( Exception $e ) {

            /* PROVIDED KEY IS NOT VALID OR WRONG, Key IS NOT SET */
            Log::doLog( $e->getMessage() );
            throw $e;

        }

        return $isValid;

    }

    /**
     * Create a new MyMemory Key
     *
     * @return stdClass
     * @throws Exception
     */
    public function createMyMemoryKey() {

        try {
            $newUser = $this->mymemory_engine->createMyMemoryKey();
        } catch ( Exception $e ) {
            //            Log::doLog( $e->getMessage() );
            throw new Exception( $e->getMessage(), -7 );
        }

        return $newUser;

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
            $uploadedFiles = $uploadManager->uploadFiles( $_FILES );

        } catch ( Exception $e ) {
//            Log::doLog( $e->getMessage() );
            throw new Exception( $e->getMessage(), -8 );
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

        Log::doLog( $this->file );

        //if there are files, add them into MyMemory
        if ( count( $this->file ) > 0 ) {

            foreach ( $this->file as $k => $fileInfo ) {

                $importStatus = $this->mymemory_engine->import(
                        $fileInfo->file_path,
                        $this->tm_key,
                        $this->name
                );

                //check for errors during the import
                switch ( $importStatus->responseStatus ) {
                    case "503" :
                    case "400" :
                        throw new Exception( "Error uploading TMX file. Please, try again in 5 minutes.", -15 );
                        break;
                    case "403" :
                        throw new Exception( "Invalid key provided", -15 );
                        break;
                    default:
                }
            }

            return true;

        }
        else {
            throw new Exception( "Can't find uploaded TMX files", -15 );
        }

    }

    /**
     * Import TMX file in MyMemory
     * @return bool
     * @throws Exception
     */
    public function addGlossaryInMyMemory() {

        $this->checkCorrectKey();

        Log::doLog( $this->file );

        //if there are files, add them into MyMemory
        if ( count( $this->file ) > 0 ) {

            foreach ( $this->file as $k => $fileInfo ) {

                $importStatus = $this->mymemory_engine->glossaryImport(
                        $fileInfo->file_path,
                        $this->tm_key,
                        $this->name
                );

                //check for errors during the import
                /**
                 * @var $importStatus Engines_Results_MyMemory_TmxResponse
                 */
                switch ( $importStatus->responseStatus ) {
                    case "400" :
                        throw new Exception( "Can't load Glossary file right now, try later", -15 );
                        break;
                    case "403" :
                        throw new Exception( "Invalid key provided", -15 );
                        break;
                    case "406" :
                        throw new Exception( $importStatus->responseDetails, -15 );
                        break;
                    default:
                }
            }

            return true;

        }
        else {
            throw new Exception( "Can't find uploaded Glossary files", -15 );
        }

    }

    /**
     * Poll this function to know the status of a TMX upload
     *
     */
    public function tmxUploadStatus() {

        //remove spaces because of MyMemory remove them and status check does not works
        $name_space_replaced = str_replace( " ", "_", $this->name );

        $allMemories = $this->mymemory_engine->getStatus( $this->tm_key, $name_space_replaced );

//        Log::doLog( $allMemories );

        if ( $allMemories->responseStatus != "200" || count( $allMemories->responseData[ 'tm' ] ) == 0 ) {

            Log::doLog( "Can't find TMX files to check for status" );

            //what the hell? No memories although I've just loaded some? Eject!
            throw new Exception( "Can't find TMX files to check for status", -15 );
        }

        $tmx_max_id = 0;
        $current_tm = array();

        //scan through memories
        foreach ( $allMemories->responseData[ 'tm' ] as $memory ) {
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
                        "done"        => $current_tm[ "temp_seg_ins" ],
                        "total"       => $current_tm[ "num_seg_tot" ],
                        "source_lang" => $current_tm[ "source_lang" ],
                        "target_lang" => $current_tm[ "target_lang" ],
                        'completed'   => false
                );
                $result[ 'completed' ] = false;
                break;
            case "1":
                //loaded (or error, in any case go ahead)
                Log::doLog( "\"" . $current_tm[ 'file_name' ] . "\" has been loaded into MyMemory" );
                $result[ 'data' ]      = array(
                        "done"        => $current_tm[ "temp_seg_ins" ],
                        "total"       => $current_tm[ "num_seg_tot" ],
                        "source_lang" => $current_tm[ "source_lang" ],
                        "target_lang" => $current_tm[ "target_lang" ],
                        'completed'   => true
                );
                $result[ 'completed' ] = true;
                break;
            default:
                throw new Exception( "Invalid TMX (\"" . $current_tm[ 'file_name' ] . "\")", -14 );
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
     * @param stdClass[] $file
     *
     * <code>
     *   //required
     *   $file->file_path
     * </code>
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

    /**
     * Set a cyclic barrier to get response about status succes to call the download
     *
     * @return resource
     * @throws Exception
     */
    public function downloadTMX() {

        /**
         * @var $result Engines_Results_MyMemory_ExportResponse
         */
        $result = $this->mymemory_engine->createExport(
                $this->tm_key
        );

        if ( $result->responseDetails == 'QUEUED' &&
                $result->responseStatus == 202
        ) {

            do {

                /**
                 * @var $result Engines_Results_MyMemory_ExportResponse
                 */
                $result = $this->mymemory_engine->checkExport( $this->tm_key );

                usleep( 1500000 ); // 1.5 seconds

            } while ( $result->responseDetails != 'READY' && $result->responseDetails != 'NO SEGMENTS' );

            if ( !isset( $result->responseDetails ) ) {
                throw new Exception( "Status check failed. Export broken.", -16 );
            }

            if ( $result->responseDetails == 'NO SEGMENTS' ) {
                throw new DomainException( "No translation memories found to download.", -17 );
            }

            $_download_url = parse_url( $result->resourceLink );
            parse_str( $_download_url[ 'query' ], $secrets );
            list( $_key, $pass ) = array_values( $secrets );

        }
        else {

            throw new Exception( "Critical. Export Creation Failed.", -18 );

        }

        $resource_pointer = $this->mymemory_engine->downloadExport( $this->tm_key, $pass );

        return $resource_pointer;

    }

    /**
     * Send a mail with link for direct prepared download
     *
     * @param $userMail
     * @param $userName
     * @param $userSurname
     *
     * @return Engines_Results_MyMemory_ExportResponse
     */
    public function requestTMXEmailDownload( $userMail, $userName, $userSurname ){

        $response = $this->mymemory_engine->emailExport(
                $this->tm_key,
                $this->name,
                $userMail,
                $userName,
                $userSurname
        );

        return $response;

    }

    public function downloadGlossary(){
        $fileName = "/tmp/GLOSS_" . $this->tm_key;
        $fHandle = $this->mymemory_engine->downloadExport( $this->tm_key, null, true, $fileName );
        fclose( $fHandle ); //flush data and close
        return $fileName;
    }

    /**
     * Export Job as Tmx File
     *
     * @param $jid
     * @param $jPassword
     * @param $sourceLang
     * @param $targetLang
     *
     * @return SplTempFileObject $tmpFile
     *
     */
    public function exportJobAsTMX( $jid, $jPassword, $sourceLang, $targetLang ) {

        $tmpFile = new SplTempFileObject( 15 * 1024 * 1024 /* 5MB */ );

        $tmpFile->fwrite( '<?xml version="1.0" encoding="UTF-8"?>
<tmx version="1.4">
    <header
            creationtool="Matecat-Cattool"
            creationtoolversion="' . INIT::$BUILD_NUMBER . '"
	    o-tmf="Matecat"
            creationid="Matecat"
            datatype="plaintext"
            segtype="sentence"
            adminlang="en-US"
            srclang="' . $sourceLang . '"/>
    <body>' );

        /*
         * This is a feature for Xbench compatibility
         * in case of mt and tm ( OmegaT set this flg to false )
         */
        $hideUnconfirmedRows = true;

        switch ( $this->output_type ) {

            case 'translation':
                $result = getTranslationsForTMXExport( $jid, $jPassword );
                break;
            case 'mt' :
                $hideUnconfirmedRows = false;
                $result = getMTForTMXExport( $jid, $jPassword );
                break;
            case 'tm' :
                $hideUnconfirmedRows = false;
                $result = getTMForTMXExport( $jid, $jPassword );
                break;
            default:
                $result = getTranslationsForTMXExport( $jid, $jPassword );
                break;
        }

        /**
         * @var $chunks Chunks_ChunkStruct[]
         */
        $chunks = Chunks_ChunkDao::getByJobID($jid);

        foreach ( $result as $k => $row ) {

            /**
             * evaluate the incremental chunk index.
             * If there's more than 1 chunk, add a 'id_chunk' prop to the segment
             */
            $idChunk = 1;
            $chunkPropString = '';
            if(count($chunks) > 1) {
                foreach ( $chunks as $i => $chunk ) {
                    if ( $row[ 'id_segment' ] >= $chunk->job_first_segment &&
                            $row[ 'id_segment' ] <= $chunk->job_last_segment
                    ) {
                        $idChunk = $i + 1;
                        break;
                    }
                }
                $chunkPropString = '<prop type="x-MateCAT-id_chunk">' . $idChunk . '</prop>';
            }
            $dateCreate = new DateTime( $row[ 'translation_date' ], new DateTimeZone( 'UTC' ) );

            $tmx = '
    <tu tuid="' . $row[ 'id_segment' ] . '" creationdate="' . $dateCreate->format( 'Ymd\THis\Z' ) . '" datatype="plaintext" srclang="' . $sourceLang . '">
        <prop type="x-MateCAT-id_job">' . $row[ 'id_job' ] . '</prop>
        <prop type="x-MateCAT-id_segment">' . $row[ 'id_segment' ] . '</prop>
        <prop type="x-MateCAT-filename">' . CatUtils::rawxliff2rawview( $row[ 'filename' ] ) . '</prop>
        <prop type="x-MateCAT-status">' . $row[ 'status' ] . '</prop>
        '.$chunkPropString.'
        <tuv xml:lang="' . $sourceLang . '">
            <seg>' . CatUtils::rawxliff2rawview( $row[ 'segment' ] ) . '</seg>
        </tuv>';

            //if segment is confirmed or we want show all segments
            if( array_search( $row[ 'status' ],
                            array(
                                    Constants_TranslationStatus::STATUS_TRANSLATED,
                                    Constants_TranslationStatus::STATUS_APPROVED,
                                    Constants_TranslationStatus::STATUS_FIXED
                            )
                    ) !== false || !$hideUnconfirmedRows ){

                $tmx .= '
        <tuv xml:lang="' . $targetLang . '">
            <seg>' . CatUtils::rawxliff2rawview( $row[ 'translation' ] ) . '</seg>
        </tuv>';

            }

            $tmx .= '
    </tu>
';

            $tmpFile->fwrite( $tmx );

        }

        $tmpFile->fwrite( "
    </body>
</tmx>" );

        $tmpFile->rewind();

        return $tmpFile;

    }

    /**
     * Export Job as Tmx File
     *
     * @param $jid
     * @param $jPassword
     * @param $sourceLang
     * @param $targetLang
     *
     * @return SplTempFileObject $tmpFile
     *
     */
    public function exportJobAsCSV( $jid, $jPassword, $sourceLang, $targetLang ) {

        $tmpFile = new SplTempFileObject( 15 * 1024 * 1024 /* 15MB */ );

        $csv_fields = array(
                "Source: $sourceLang", "Target: $targetLang"
        );

        $tmpFile->fputcsv( $csv_fields );

        $result = getTranslationsForTMXExport( $jid, $jPassword );

        foreach ( $result as $k => $row ) {

            $row_array = array(
                    $row[ 'segment' ], $row[ 'translation' ]
            );

            $tmpFile->fputcsv( $row_array );

        }

        $tmpFile->rewind();

        return $tmpFile;

    }

}
