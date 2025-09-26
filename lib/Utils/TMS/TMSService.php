<?php

namespace Utils\TMS;

use Controller\API\Commons\Exceptions\UnprocessableException;
use DateTime;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use Matecat\SubFiltering\MateCatFilter;
use Model\Conversion\Upload;
use Model\Conversion\UploadElement;
use Model\Engines\Structs\EngineStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\ChunkDao;
use Model\Jobs\JobDao;
use Model\Projects\MetadataDao as ProjectMetadataDao;
use Model\TMSService\TMSServiceDao;
use Model\Users\MetadataDao;
use Model\Users\UserStruct;
use ReflectionException;
use SplFileInfo;
use SplTempFileObject;
use Utils\Constants\EngineConstants;
use Utils\Constants\TranslationStatus;
use Utils\Engines\EnginesFactory;
use Utils\Engines\MyMemory;
use Utils\Engines\Results\MyMemory\CreateUserResponse;
use Utils\Engines\Results\MyMemory\ExportResponse;
use Utils\Logger\LoggerFactory;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;
use Utils\Tools\Utils;

class TMSService {

    /**
     * @var FeatureSet|null
     */
    protected ?FeatureSet $featureSet;

    /**
     * @var string The name of the uploaded TMX
     */
    protected string $name = '';

    /**
     * @var MyMemory
     */
    protected MyMemory $mymemory_engine;

    private string          $output_type;
    protected MatecatLogger $logger;

    /**
     *
     * @param FeatureSet|null $featureSet
     *
     * @throws Exception
     */
    public function __construct( ?FeatureSet $featureSet = null ) {

        //get Match service
        /** @var $mymemory_engine MyMemory */
        $mymemory_engine       = EnginesFactory::getInstance( 1 );
        $this->mymemory_engine = $mymemory_engine;

        $this->output_type = 'translation';

        if ( $featureSet == null ) {
            $featureSet = new FeatureSet();
        }
        $this->featureSet = $featureSet;

        /**
         * Set the initial value to a specific log file, if not already initialized by the Executor.
         * This is useful when engines are used outside the TaskRunner context
         * @see \Utils\TaskRunner\Executor::__construct()
         */
        $this->logger = LoggerFactory::getLogger( 'engines' );

    }

    /**
     * @param string $output_type
     */
    public function setOutputType( string $output_type ) {
        $this->output_type = $output_type;
    }

    /**
     * Check for key correctness
     *
     * @throws Exception
     */
    public function checkCorrectKey( string $tm_key ): ?bool {

        //validate the key
        //This piece of code must be executed every time
        try {

            $isValid = $this->mymemory_engine->checkCorrectKey( $tm_key );

        } catch ( Exception $e ) {

            /* PROVIDED KEY IS NOT VALID OR WRONG, Key IS NOT SET */
            $this->logger->debug( $e->getMessage() );
            throw $e;

        }

        return $isValid;

    }

    /**
     * Create a new Match Key
     *
     * @return CreateUserResponse
     * @throws Exception
     */
    public function createMyMemoryKey(): CreateUserResponse {

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
     * @return UploadElement
     * @throws Exception
     */
    public function uploadFile(): UploadElement {
        $uploadManager = new Upload();

        return $uploadManager->uploadFiles( $_FILES );
    }

    /**
     * Import TMX file in Match
     * @throws Exception
     */
    public function addTmxInMyMemory( TMSFile $file, UserStruct $user ): array {

        try {

            $this->checkCorrectKey( $file->getTmKey() );

            $this->logger->debug( $file );

            $importStatus = $this->mymemory_engine->importMemory(
                    $file->getFilePath(),
                    $file->getTmKey(),
                    $user
            );

            //check for errors during the import
            switch ( $importStatus->responseStatus ) {
                case "503" :
                case "400" :
                    throw new Exception( "Error uploading TMX file. Please, try again in 5 minutes.", -15 );
                case "403" :
                    throw new Exception( "Error: " . $this->formatErrorMessage( $importStatus->responseDetails ), -15 );
                default:
            }

            $file->setUuid( $importStatus->id );

            // load tmx in engines with adaptivity
            $engineList = EngineConstants::getAvailableEnginesList();

            $warnings = [];

            foreach ( $engineList as $engineName ) {

                try {

                    $struct             = EngineStruct::getStruct();
                    $struct->class_load = $engineName;
                    $struct->type       = EngineConstants::MT;
                    $engine             = EnginesFactory::createTempInstance( $struct );

                    if ( $engine->isAdaptiveMT() ) {
                        //retrieve OWNER EnginesFactory License
                        $ownerMmtEngineMetaData = ( new MetadataDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->get( $user->uid, $engine->getEngineRecord()->class_load ); // engine_id
                        if ( !empty( $ownerMmtEngineMetaData ) ) {
                            $engine = EnginesFactory::getInstance( $ownerMmtEngineMetaData->value );

                            $this->logger->debug( "User [$user->uid, '$user->email'] start importing memory: {$engine->getEngineRecord()->class_load} -> " . $file->getFilePath() . " -> " . $file->getTmKey() );
                            $engine->importMemory( $file->getFilePath(), $file->getTmKey(), $user );

                        }
                    }

                } catch ( Exception $e ) {
                    if ( $engineName != EngineConstants::MY_MEMORY ) {
                        //NOTICE: ModernMT response is 404 NOT FOUND if the key on which we are importing the tmx is not synced with it
                        $this->logger->debug( $e->getMessage() );
                        $engineName = explode( "\\", $engineName );
                        $engineName = end( $engineName );
                        $warnings[] = [ 'engine' => $engineName, 'message' => $e->getMessage(), 'file' => $file->getName() ];
                    }
                }

            }

        } finally {
            @unlink( $file->getFilePath() );
            @unlink( $file->getFilePath() . ".gz" );
        }

        return $warnings;

    }

    /**
     * Import TMX file in Match
     *
     * @param TMSFile $file
     *
     * @throws Exception
     */
    public function addGlossaryInMyMemory( TMSFile $file ) {

        $this->checkCorrectKey( $file->getTmKey() );

        $this->logger->debug( $file );

        $importStatus = $this->mymemory_engine->glossaryImport(
                $file->getFilePath(),
                $file->getTmKey(),
                $file->getName()
        );

        //check for errors during the import
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

                $message = 'Invalid TM key provided, please provide a valid Match key.';
                throw new InvalidArgumentException( $message, $importStatus->responseStatus );

            default:
        }

        $file->setUuid( $importStatus->id );

    }

    /**
     * @throws Exception
     */
    public function _fileUploadStatus( string $uuid, string $type ): array {

        $allMemories = $this->mymemory_engine->getImportStatus( $uuid );

        if ( $allMemories->responseStatus >= 400 || $allMemories->responseData[ 'status' ] == 2 ) {
            $this->logger->debug( "Error response from TMX status check: " . $allMemories->responseData[ 'log' ] );
            //what the hell? No memories, although I've just loaded some? Eject!
            throw new Exception( "Error response from TMX status check", -15 );
        }

        switch ( $allMemories->responseData[ 'status' ] ) {
            case "0":
            case "-1":
                //wait for the daemon to process it
                //LOADING
                $this->logger->debug( "waiting for \"" . $this->name . "\" to be loaded into MyMemory" );
                $result[ 'data' ]      = $allMemories->responseData;
                $result[ 'completed' ] = false;
                break;
            case "1":
                //loaded (or error, in any case go ahead)
                $this->logger->debug( "\"" . $this->name . "\" has been loaded into MyMemory" );
                $result[ 'data' ]      = $allMemories->responseData;
                $result[ 'completed' ] = true;
                break;
            default:
                throw new Exception( "Invalid $type (\"" . $this->name . "\")", -14 ); // this should never happen
        }

        return $result;

    }

    /**
     * @param string $uuid
     *
     * @return array
     * @throws Exception
     */
    public function glossaryUploadStatus( string $uuid ): array {
        return $this->_fileUploadStatus( $uuid, "Glossary" );
    }

    /**
     * @param string $key
     * @param string $keyName
     * @param string $userEmail
     * @param string $userName
     *
     * @return ExportResponse
     */
    public function glossaryExport( string $key, string $keyName, string $userEmail, string $userName ): ExportResponse {
        return $this->mymemory_engine->glossaryExport( $key, $keyName, $userEmail, $userName );
    }

    /**
     * @param string $uuid
     *
     * @return array
     * @throws Exception
     */
    public function tmxUploadStatus( string $uuid ): array {
        return $this->_fileUploadStatus( $uuid, "TMX" );
    }

    /**
     * @param string $message
     *
     * @return string
     */
    private function formatErrorMessage( string $message ): string {
        if ( $message === "THE CHARACTER SET PROVIDED IS INVALID." ) {
            return "The encoding of the TMX file uploaded is not valid, please open it in a text editor, convert its encoding to UTF-8 (character corruption might happen) and retry upload";
        }

        return $message;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName( string $name ): TMSService {
        $this->name = $name;

        return $this;
    }

    /**
     * Send mail with a link for direct prepared download
     *
     * @param string $userMail
     * @param string $userName
     * @param string $userSurname
     * @param string $tm_key
     * @param bool   $strip_tags
     *
     * @return ExportResponse
     * @throws Exception
     */
    public function requestTMXEmailDownload( string $userMail, string $userName, string $userSurname, string $tm_key, bool $strip_tags = false ): ExportResponse {

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
     * @param int      $jid
     * @param string   $jPassword
     * @param string   $sourceLang
     * @param string   $targetLang
     *
     * @param int|null $uid
     *
     * @return SplTempFileObject $tmpFile
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public function exportJobAsTMX( int $jid, string $jPassword, string $sourceLang, string $targetLang, int $uid = null ): SplFileInfo {

        $featureSet = ( $this->featureSet !== null ) ? $this->featureSet : new FeatureSet();

        $jobStruct = JobDao::getByIdAndPassword($jid, $jPassword);
        $metadata  = new ProjectMetadataDao();
        /** @var MateCatFilter $Filter */
        $Filter  = MateCatFilter::getInstance( $featureSet, $sourceLang, $targetLang, [], $metadata->getSubfilteringCustomHandlers((int)$jobStruct->id_project) );
        $tmpFile = new SplTempFileObject( 15 * 1024 * 1024 /* 5MB */ );

        $tmpFile->fwrite( '<?xml version="1.0" encoding="UTF-8"?>
<tmx version="1.4">
    <header
            creationtool="Matecat-Cattool"
            creationtoolversion="' . AppConfig::$BUILD_NUMBER . '"
	    o-tmf="Matecat"
            creationid="Matecat"
            datatype="plaintext"
            segtype="sentence"
            adminlang="en-US"
            srclang="' . $sourceLang . '"/>
    <body>' );

        /*
         * This is a feature for Xbench compatibility
         * in the case of mt and tm (OmegaT set this flg to false)
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

        $chunks = ChunkDao::getByJobID( $jid );

        foreach ( $result as $k => $row ) {

            /**
             * Evaluate the incremental chunk index.
             * If there's more than 1 chunk, add an 'id_chunk' prop to the segment
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
                $suggestionOrigin = Utils::changeMemorySuggestionSource( $suggestionsArray[ 0 ], $row[ 'tm_keys' ], $uid );
                $tmOrigin         = '<prop type="x-MateCAT-suggestion-origin">' . $suggestionOrigin . "</prop>";
                if ( preg_match( "/[a-f0-9]{8,}/", $suggestionsArray[ 0 ][ 'memory_key' ] ) ) {
                    $tmOrigin .= "\n        <prop type=\"x-MateCAT-suggestion-private-key\">" . $suggestionsArray[ 0 ][ 'memory_key' ] . "</prop>";
                }
            }

            $contextPre  = ( isset( $result[ ( $k - 1 ) ] ) ) ? $result[ ( $k - 1 ) ][ 'segment' ] : '';
            $contextPost = ( isset( $result[ ( $k + 1 ) ] ) ) ? $result[ ( $k + 1 ) ][ 'segment' ] : '';

            $tmx = '
    <tu tuid="' . $row[ 'id_segment' ] . '" creationdate="' . $dateCreate->format( 'Ymd\THis\Z' ) . '" datatype="plaintext" srclang="' . $sourceLang . '">
        <prop type="x-MateCAT-id_job">' . $row[ 'id_job' ] . '</prop>
        <prop type="x-MateCAT-id_segment">' . $row[ 'id_segment' ] . '</prop>
        <prop type="x-MateCAT-filename">' . htmlspecialchars( $row[ 'filename' ], ENT_DISALLOWED, "UTF-8" ) . '</prop>
        <prop type="x-MateCAT-status">' . $row[ 'status' ] . '</prop>
        <prop type="x-context-pre">' . $contextPre . '</prop>
        <prop type="x-context-post">' . $contextPost . '</prop>
        ' . $chunkPropString . '
        ' . $tmOrigin . '
        <tuv xml:lang="' . $sourceLang . '">
            <seg>' . $Filter->fromLayer0ToRawXliff( $row[ 'segment' ] ) . '</seg>
        </tuv>';

            //if the segment is confirmed, or we want to show all the segments
            if ( in_array( $row[ 'status' ],
                            [
                                    TranslationStatus::STATUS_TRANSLATED,
                                    TranslationStatus::STATUS_APPROVED,
                                    TranslationStatus::STATUS_APPROVED2,
                                    TranslationStatus::STATUS_FIXED
                            ] ) || !$hideUnconfirmedRows ) {

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
    public function exportJobAsCSV( $jid, $jPassword, $sourceLang, $targetLang ): SplTempFileObject {

        $tmpFile = new SplTempFileObject( 15 * 1024 * 1024 /* 15MB */ );

        $csv_fields = [
                "Source: $sourceLang", "Target: $targetLang"
        ];

        $tmpFile->fputcsv( $csv_fields );

        $result = TMSServiceDao::getTranslationsForTMXExport( $jid, $jPassword );

        foreach ( $result as $row ) {

            $row_array = [
                    $row[ 'segment' ], $row[ 'translation' ]
            ];

            $tmpFile->fputcsv( $row_array );

        }

        $tmpFile->rewind();

        return $tmpFile;

    }

}
