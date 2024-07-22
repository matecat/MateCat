<?php

namespace TMS;

use API\V2\Exceptions\UnprocessableException;
use Chunks_ChunkDao;
use Chunks_ChunkStruct;
use Constants_TranslationStatus;
use DateTime;
use DateTimeZone;
use Engine;
use Engines_MyMemory;
use Engines_Results_MyMemory_ExportResponse;
use Engines_Results_MyMemory_TmxResponse;
use Exception;
use FeatureSet;
use INIT;
use InvalidArgumentException;
use Log;
use Matecat\SubFiltering\MateCatFilter;
use SplTempFileObject;
use stdClass;
use TMSService\TMSServiceDao;
use Upload;
use Utils;

class TMSService {

    /**
     * @var FeatureSet
     */
    protected $featureSet;

    /**
     * @var string The name of the uploaded TMX
     */
    protected $name;

    /**
     * @var TMSFile[]
     */
    private $file;

    /**
     * @var Engines_MyMemory
     */
    protected $mymemory_engine;

    private $output_type;

    /**
     *
     * @param FeatureSet|null $featureSet
     *
     * @throws Exception
     */
    public function __construct( FeatureSet $featureSet = null ) {

        //get MyMemory service
        $this->mymemory_engine = Engine::getInstance( 1 );

        $this->output_type = 'translation';

        if ( $featureSet == null ) {
            $featureSet = new FeatureSet();
        }
        $this->featureSet = $featureSet;

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
    public function checkCorrectKey( $tm_key ) {

        //validate the key
        //This piece of code need to be executed every time
        try {

            $isValid = $this->mymemory_engine->checkCorrectKey( $tm_key );

        } catch ( Exception $e ) {

            /* PROVIDED KEY IS NOT VALID OR WRONG, Key IS NOT SET */
            Log::doJsonLog( $e->getMessage() );
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
            //            Log::doJsonLog( $e->getMessage() );
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
//            Log::doJsonLog( $e->getMessage() );
            throw new Exception( $e->getMessage(), -8 );
        }

        return $this->file = $uploadedFiles;
    }

    /**
     * Import TMX file in MyMemory
     * @throws Exception
     */
    public function addTmxInMyMemory( TMSFile $file ) {

        $this->checkCorrectKey( $file->getTmKey() );

        Log::doJsonLog( $this->file );

        $importStatus = $this->mymemory_engine->import(
                $file->getFilePath(),
                $file->getTmKey(),
                $file->getName()
        );

        //check for errors during the import
        switch ( $importStatus->responseStatus ) {
            case "503" :
            case "400" :
                throw new Exception( "Error uploading TMX file. Please, try again in 5 minutes.", -15 );
            case "403" :
                throw new Exception( "Error: ". $this->formatErrorMessage($importStatus->responseDetails), -15 );
            default:
        }

        $file->setUuid( $importStatus->id );

    }

    /**
     * Import TMX file in MyMemory
     * @param TMSFile $file
     * @throws Exception
     */
    public function addGlossaryInMyMemory( TMSFile $file ) {

        $this->checkCorrectKey( $file->getTmKey() );

        Log::doJsonLog( $this->file );

        $importStatus = $this->mymemory_engine->glossaryImport(
                $file->getFilePath(),
                $file->getTmKey(),
                $file->getName()
        );

        //check for errors during the import
        /**
         * @var $importStatus Engines_Results_MyMemory_TmxResponse
         */
        switch ( $importStatus->responseStatus ) {
            case "400" :
                throw new Exception( "Can't load Glossary file right now, try later", -15 );
            case "404":
                throw new InvalidArgumentException( 'File format not supported, please upload a glossary in XLSX, XLS or ODS format.', -15 );
            case "406":
                throw new InvalidArgumentException( $importStatus->responseDetails, -15 );
            case "403" :

                if ( $importStatus->responseDetails === 'HEADER DON\'T MATCH THE CORRECT STRUCTURE' ) {
                    $message = 'The file header does not match the accepted structure. Please change the header structure to the one set out in <a href="https://guides.matecat.com/glossary-file-format" target="_blank">the user guide page</a> and retry upload.';
                    throw new UnprocessableException( $message, $importStatus->responseStatus );
                }

                $message = 'Invalid TM key provided, please provide a valid MyMemory key.';
                throw new InvalidArgumentException( $message, $importStatus->responseStatus );

            default:
        }

        $file->setUuid( $importStatus->id );

    }

    /**
     * @param $uuid
     * @return mixed
     * @throws Exception
     */
    public function glossaryUploadStatus( $uuid ) {

        $allMemories = $this->mymemory_engine->getGlossaryImportStatus( $uuid );

        if ( $allMemories->responseStatus >= 400 || $allMemories->responseData[ 'status' ] == 2 ) {
            Log::doJsonLog( "Error response from TMX status check: " . $allMemories->responseData[ 'log' ] );
            //what the hell? No memories although I've just loaded some? Eject!
            throw new Exception( "Error response from TMX status check", -15 );
        }

        switch ( $allMemories->responseData[ 'status' ] ) {
            case "0":
            case "-1":
                //wait for the daemon to process it
                //LOADING
                Log::doJsonLog( "waiting for \"" . $this->name . "\" to be loaded into MyMemory" );
                $result[ 'data' ]      = $allMemories->responseData;
                $result[ 'completed' ] = false;
                break;
            case "1":
                //loaded (or error, in any case go ahead)
                Log::doJsonLog( "\"" . $this->name . "\" has been loaded into MyMemory" );
                $result[ 'data' ]      = $allMemories->responseData;
                $result[ 'completed' ] = true;
                break;
            default:
                throw new Exception( "Invalid Glossary (\"" . $this->name . "\")", -14 ); // this should never happen
        }

        return $result;

    }

    /**
     * @param $key
     * @param $keyName
     * @param $userEmail
     * @param $userName
     *
     * @return Engines_Results_MyMemory_ExportResponse
     */
    public function glossaryExport( $key, $keyName, $userEmail, $userName ) {
        return $this->mymemory_engine->glossaryExport( $key, $keyName, $userEmail, $userName );
    }

    /**
     * @return array
     * @throws Exception
     */
    public function tmxUploadStatus( $uuid ) {

        $allMemories = $this->mymemory_engine->getStatus( $uuid );

        if ( $allMemories->responseStatus >= 400 || $allMemories->responseData[ 'status' ] == 2 ) {
            Log::doJsonLog( "Error response from TMX status check: " . $allMemories->responseData[ 'log' ] );
            //what the hell? No memories although I've just loaded some? Eject!
            throw new Exception( 'Error: '. $this->formatErrorMessage($allMemories->responseData[ 'log' ]), -15 );
        }

        switch ( $allMemories->responseData[ 'status' ] ) {
            case "0":
            case "-1":
                //wait for the daemon to process it
                //LOADING
                Log::doJsonLog( "waiting for \"" . $this->name . "\" to be loaded into MyMemory" );
                $result[ 'data' ]      = $allMemories->responseData;
                $result[ 'completed' ] = false;
                break;
            case "1":
                //loaded (or error, in any case go ahead)
                Log::doJsonLog( "\"" . $this->name . "\" has been loaded into MyMemory" );
                $result[ 'data' ]      = $allMemories->responseData;
                $result[ 'completed' ] = true;
                break;
            default:
                throw new Exception( "Invalid TMX (\"" . $this->name . "\")", -14 ); // this should never happen
        }

        return $result;

    }

    /**
     * @param $message
     * @return mixed
     */
    private function formatErrorMessage($message)
    {
        if($message === "THE CHARACTER SET PROVIDED IS INVALID."){
            return "The encoding of the TMX file uploaded is not valid, please open it in a text editor, convert its encoding to UTF-8 (character corruption might happen) and retry upload";
        }

        return $message;
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
     * @param TMSFile[] $file
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
     * Send a mail with link for direct prepared download
     *
     * @param $userMail
     * @param $userName
     * @param $userSurname
     * @param $tm_key
     * @param bool $strip_tags
     *
     * @return Engines_Results_MyMemory_ExportResponse
     * @throws Exception
     */
    public function requestTMXEmailDownload( $userMail, $userName, $userSurname, $tm_key, $strip_tags = false ) {

        return $this->mymemory_engine->emailExport(
                $tm_key,
                $this->name,
                $userMail,
                $userName,
                $userSurname,
                $strip_tags
        );
    }

    /**
     * Export Job as Tmx File
     *
     * @param          $jid
     * @param          $jPassword
     * @param          $sourceLang
     * @param          $targetLang
     *
     * @param int|null $uid
     *
     * @return SplTempFileObject $tmpFile
     *
     * @throws Exception
     */
    public function exportJobAsTMX( $jid, $jPassword, $sourceLang, $targetLang, $uid = null ) {

        $featureSet = ( $this->featureSet !== null ) ? $this->featureSet : new FeatureSet();
        $Filter     = MateCatFilter::getInstance( $featureSet, $sourceLang, $targetLang, [] );
        $tmpFile    = new SplTempFileObject( 15 * 1024 * 1024 /* 5MB */ );

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

            case 'mt' :
                $hideUnconfirmedRows = false;
                $result              = TMSServiceDao::getMTForTMXExport( $jid, $jPassword );
                break;
            case 'tm' :
                $hideUnconfirmedRows = false;
                $result              = TMSServiceDao::getTMForTMXExport( $jid, $jPassword );
                break;
            case 'translation':
            default:
                $result = TMSServiceDao::getTranslationsForTMXExport( $jid, $jPassword );
                break;
        }

        /**
         * @var $chunks Chunks_ChunkStruct[]
         */
        $chunks = Chunks_ChunkDao::getByJobID( $jid );

        foreach ( $result as $k => $row ) {

            /**
             * evaluate the incremental chunk index.
             * If there's more than 1 chunk, add a 'id_chunk' prop to the segment
             */
            $idChunk         = 1;
            $chunkPropString = '';
            if ( count( $chunks ) > 1 ) {
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

            $tmOrigin = "";
            if ( strpos( $this->output_type, 'tm' ) !== false ) {
                $suggestionsArray = json_decode( $row[ 'suggestions_array' ], true );
                $suggestionOrigin = Utils::changeMemorySuggestionSource( $suggestionsArray[ 0 ], $row[ 'tm_keys' ], $row[ 'id_customer' ], $uid );
                $tmOrigin         = '<prop type="x-MateCAT-suggestion-origin">' . $suggestionOrigin . "</prop>";
                if ( preg_match( "/[a-f0-9]{8,}/", $suggestionsArray[ 0 ][ 'memory_key' ] ) ) {
                    $tmOrigin .= "\n        <prop type=\"x-MateCAT-suggestion-private-key\">" . $suggestionsArray[ 0 ][ 'memory_key' ] . "</prop>";
                }
            }

            $contextPre = (isset($result[($k-1)])) ? $result[($k-1)]['segment'] : '';
            $contextPost = (isset($result[($k+1)])) ? $result[($k+1)]['segment'] : '';

            $tmx = '
    <tu tuid="' . $row[ 'id_segment' ] . '" creationdate="' . $dateCreate->format( 'Ymd\THis\Z' ) . '" datatype="plaintext" srclang="' . $sourceLang . '">
        <prop type="x-MateCAT-id_job">' . $row[ 'id_job' ] . '</prop>
        <prop type="x-MateCAT-id_segment">' . $row[ 'id_segment' ] . '</prop>
        <prop type="x-MateCAT-filename">' . htmlspecialchars( $row[ 'filename' ], ENT_DISALLOWED, "UTF-8" ) . '</prop>
        <prop type="x-MateCAT-status">' . $row[ 'status' ] . '</prop>
        <prop type="x-context-pre">'.$contextPre.'</prop>
        <prop type="x-context-post">'.$contextPost.'</prop>
        ' . $chunkPropString . '
        ' . $tmOrigin . '
        <tuv xml:lang="' . $sourceLang . '">
            <seg>' . $Filter->fromLayer0ToRawXliff( $row[ 'segment' ] ) . '</seg>
        </tuv>';

            //if segment is confirmed or we want show all segments
            if ( array_search( $row[ 'status' ],
                            [
                                    Constants_TranslationStatus::STATUS_TRANSLATED,
                                    Constants_TranslationStatus::STATUS_APPROVED,
                                    Constants_TranslationStatus::STATUS_APPROVED2,
                                    Constants_TranslationStatus::STATUS_FIXED
                            ]
                    ) !== false || !$hideUnconfirmedRows ) {

                $tmx .= '
        <tuv xml:lang="' . $targetLang . '">
            <seg>' . $Filter->fromLayer0ToRawXliff( $row[ 'translation' ] ) . '</seg>
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

        $csv_fields = [
                "Source: $sourceLang", "Target: $targetLang"
        ];

        $tmpFile->fputcsv( $csv_fields );

        $result = TMSServiceDao::getTranslationsForTMXExport( $jid, $jPassword );

        foreach ( $result as $k => $row ) {

            $row_array = [
                    $row[ 'segment' ], $row[ 'translation' ]
            ];

            $tmpFile->fputcsv( $row_array );

        }

        $tmpFile->rewind();

        return $tmpFile;

    }

}
