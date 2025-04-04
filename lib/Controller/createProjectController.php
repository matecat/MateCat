<?php

use ConnectedServices\Google\GDrive\Session;
use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;
use Langs\Languages;
use Matecat\XliffParser\Utils\Files as XliffFiles;
use Matecat\XliffParser\XliffUtils\XliffProprietaryDetect;
use PayableRates\CustomPayableRateDao;
use PayableRates\CustomPayableRateStruct;
use ProjectQueue\Queue;
use QAModelTemplate\QAModelTemplateDao;
use QAModelTemplate\QAModelTemplateStruct;
use Validator\EngineValidator;
use Validator\JSONValidator;
use Validator\JSONValidatorObject;
use Validator\MMTValidator;
use Xliff\XliffConfigTemplateDao;

class createProjectController extends ajaxController {

    private $mmt_glossaries;
    private $deepl_id_glossary;
    private $deepl_formality;
    private $file_name;
    private $project_name;
    private $source_lang;
    private $target_lang;
    private $job_subject;
    private $mt_engine;
    private $tms_engine = 1;  //1 default MyMemory
    private $private_tm_key;
    private $disable_tms_engine_flag;
    private $pretranslate_100;
    private $pretranslate_101;
    private $only_private;
    private $due_date;
    private $metadata;
    private $dialect_strict;
    private $filters_extraction_parameters;
    private $xliff_parameters;

    // LEGACY PARAMS TO BE REMOVED
    private $dictation;
    private $show_whitespace;
    private $character_counter;
    private $ai_assistant;

    private $character_counter_count_tags;
    private $character_counter_mode;

    /**
     * @var QAModelTemplateStruct
     */
    private $qaModelTemplate;

    /**
     * @var CustomPayableRateStruct
     */
    private $payableRateModelTemplate;

    /**
     * @var \Teams\TeamStruct
     */
    private $team;

    /**
     * @var BasicFeatureStruct[]
     */
    private $projectFeatures = [];

    public $postInput;

    private $tm_prioritization;

    /**
     * @throws Exception
     */
    public function __construct() {

        //SESSION ENABLED
        parent::__construct();

        $filterArgs = [
                'file_name'          => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'project_name'       => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'source_lang'        => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'target_lang'        => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'job_subject'        => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'due_date'           => [ 'filter' => FILTER_VALIDATE_INT ],
                'mt_engine'          => [ 'filter' => FILTER_VALIDATE_INT ],
                'disable_tms_engine' => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'private_tm_key'     => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'pretranslate_100'   => [ 'filter' => FILTER_VALIDATE_INT ],
                'pretranslate_101'   => [ 'filter' => FILTER_VALIDATE_INT ],
                'tm_prioritization'  => [ 'filter' => FILTER_VALIDATE_INT ],
                'id_team'            => [ 'filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR ],

                'mmt_glossaries' => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],

                'deepl_id_glossary'             => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'deepl_formality'               => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'project_completion'            => [ 'filter' => FILTER_VALIDATE_BOOLEAN ], // features customization
                'get_public_matches'            => [ 'filter' => FILTER_VALIDATE_BOOLEAN ], // disable public TM matches
                'dictation'                     => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'show_whitespace'               => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'character_counter'             => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'character_counter_count_tags'  => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'character_counter_mode'        => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ],
                'ai_assistant'                  => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'dialect_strict'                => [ 'filter' => FILTER_SANITIZE_STRING ],
                'filters_extraction_parameters' => [ 'filter' => FILTER_SANITIZE_STRING ],
                'xliff_parameters'              => [ 'filter' => FILTER_SANITIZE_STRING ],
                'xliff_parameters_template_id'  => [ 'filter' => FILTER_VALIDATE_INT ],

                'qa_model_template_id'     => [ 'filter' => FILTER_VALIDATE_INT ],
                'payable_rate_template_id' => [ 'filter' => FILTER_VALIDATE_INT ],
        ];

        $this->identifyUser();
        $this->setupUserFeatures();

        $filterArgs = $this->__addFilterForMetadataInput( $filterArgs );

        $this->postInput = filter_input_array( INPUT_POST, $filterArgs );



        //first we check the presence of a list from tm management panel

        $this->__validateTMKeysArray($_POST[ 'private_keys_list' ]);

        $array_keys = json_decode( $_POST[ 'private_keys_list' ], true );
        $array_keys = array_merge( $array_keys[ 'ownergroup' ], $array_keys[ 'mine' ], $array_keys[ 'anonymous' ] );

        //if a string is sent by the client, transform it into a valid array
        if ( !empty( $this->postInput[ 'private_tm_key' ] ) ) {
            $this->postInput[ 'private_tm_key' ] = [
                    [
                            'key'  => trim( $this->postInput[ 'private_tm_key' ] ),
                            'name' => null,
                            'r'    => true,
                            'w'    => true
                    ]
            ];
        } else {
            $this->postInput[ 'private_tm_key' ] = [];
        }

        if ( $array_keys ) { // some keys are selected from panel

            //remove duplicates
            foreach ( $array_keys as $pos => $value ) {
                if ( isset( $this->postInput[ 'private_tm_key' ][ 0 ][ 'key' ] )
                        && $this->postInput[ 'private_tm_key' ][ 0 ][ 'key' ] == $value[ 'key' ]
                ) {
                    //same key was get from keyring, remove
                    $this->postInput[ 'private_tm_key' ] = [];
                }
            }

            //merge the arrays
            $private_keyList = array_merge( $this->postInput[ 'private_tm_key' ], $array_keys );


        } else {
            $private_keyList = $this->postInput[ 'private_tm_key' ];
        }

        $__postPrivateTmKey = array_filter( $private_keyList, [ "self", "sanitizeTmKeyArr" ] );

        // NOTE: This is for debug purpose only,
        // NOTE: Global $_POST Overriding from CLI
        // $this->__postInput = filter_var_array( $_POST, $filterArgs );

        $this->file_name               = $this->postInput[ 'file_name' ];       // da cambiare, FA SCHIFO la serializzazione
        $this->project_name            = $this->postInput[ 'project_name' ];
        $this->source_lang             = $this->postInput[ 'source_lang' ];
        $this->target_lang             = $this->postInput[ 'target_lang' ];
        $this->job_subject             = $this->postInput[ 'job_subject' ];
        $this->mt_engine               = ( $this->postInput[ 'mt_engine' ] != null ? $this->postInput[ 'mt_engine' ] : 0 );       // null NON è ammesso
        $this->disable_tms_engine_flag = $this->postInput[ 'disable_tms_engine' ]; // se false allora MyMemory
        $this->private_tm_key          = $__postPrivateTmKey;
        $this->pretranslate_100        = $this->postInput[ 'pretranslate_100' ];
        $this->pretranslate_101        = $this->postInput[ 'pretranslate_101' ];
        $this->only_private            = ( is_null( $this->postInput[ 'get_public_matches' ] ) ? false : !$this->postInput[ 'get_public_matches' ] );
        $this->due_date                = ( empty( $this->postInput[ 'due_date' ] ) ? null : Utils::mysqlTimestamp( $this->postInput[ 'due_date' ] ) );

        $this->dictation         = $this->postInput[ 'dictation' ] ?? null;
        $this->show_whitespace   = $this->postInput[ 'show_whitespace' ] ?? null;
        $this->character_counter = $this->postInput[ 'character_counter' ] ?? null;
        $this->ai_assistant      = $this->postInput[ 'ai_assistant' ] ?? null;
        $this->tm_prioritization = $this->postInput[ 'tm_prioritization' ] ?? null;

        $this->character_counter_count_tags = $this->postInput[ 'character_counter_count_tags' ] ?? null;
        $this->character_counter_mode       = $this->postInput[ 'character_counter_mode' ] ?? null;

        $this->__setMetadataFromPostInput();

        if ( $this->disable_tms_engine_flag ) {
            $this->tms_engine = 0; //remove default MyMemory
        }

        if ( empty( $this->file_name ) ) {
            $this->result[ 'errors' ][] = [ "code" => -1, "message" => "Missing file name." ];
        }

        if ( empty( $this->job_subject ) ) {
            $this->result[ 'errors' ][] = [ "code" => -5, "message" => "Missing job subject." ];
        }

        if ( $this->pretranslate_100 !== 1 && $this->pretranslate_100 !== 0 ) {
            $this->result[ 'errors' ][] = [ "code" => -6, "message" => "invalid pretranslate_100 value" ];
        }

        if ( $this->pretranslate_101 !== null && $this->pretranslate_101 !== 1 && $this->pretranslate_101 !== 0 ) {
            $this->result[ 'errors' ][] = [ "code" => -6, "message" => "invalid pretranslate_101 value" ];
        }


        $this->__validateSourceLang( Languages::getInstance() );
        $this->__validateTargetLangs( Languages::getInstance() );
        $this->__validateCharacterCounterMode();
        $this->__validateUserMTEngine();
        $this->__validateMMTGlossaries();
        $this->__validateDeepLGlossaryParams();
        $this->__validateQaModelTemplate();
        $this->__validatePayableRateTemplate();
        $this->__validateDialectStrictParam();
        $this->__validateFiltersExtractionParameters();
        $this->__validateXliffParameters();
        $this->__appendFeaturesToProject();
        $this->__generateTargetEngineAssociation();
        if ( $this->userIsLogged ) {
            $this->__setTeam( $this->postInput[ 'id_team' ] );
        }
    }

    /**
     * setProjectFeatures
     *
     * @throws \Exceptions\NotFoundException
     * @throws \API\Commons\Exceptions\AuthenticationError
     * @throws \Exceptions\ValidationError
     * @throws \TaskRunner\Exceptions\EndQueueException
     * @throws \TaskRunner\Exceptions\ReQueueException
     */

    private function __appendFeaturesToProject() {
        // change project features

        if ( !empty( $this->postInput[ 'project_completion' ] ) ) {
            $feature                 = new BasicFeatureStruct();
            $feature->feature_code   = 'project_completion';
            $this->projectFeatures[] = $feature;
        }

        $this->projectFeatures = $this->featureSet->filter(
                'filterCreateProjectFeatures', $this->projectFeatures, $this
        );

    }

    /**
     * @throws Exception
     */
    public function doAction() {
        //check for errors. If there are, stop execution and return errors.
        if ( count( @$this->result[ 'errors' ] ) ) {
            return false;
        }

        $arFiles = array_filter( explode( '@@SEP@@', html_entity_decode( $this->file_name, ENT_QUOTES, 'UTF-8' ) ) );

        $default_project_name = $arFiles[ 0 ];
        if ( count( $arFiles ) > 1 ) {
            $default_project_name = "MATECAT_PROJ-" . date( "Ymdhi" );
        }

        if ( empty( $this->project_name ) ) {
            $this->project_name = $default_project_name;
        }

        // SET SOURCE COOKIE
        CookieManager::setCookie( Constants::COOKIE_SOURCE_LANG, $this->source_lang,
                [
                        'expires'  => time() + ( 86400 * 365 ),
                        'path'     => '/',
                        'domain'   => INIT::$COOKIE_DOMAIN,
                        'secure'   => true,
                        'httponly' => true,
                        'samesite' => 'None',
                ]
        );

        // SET TARGET COOKIE
        CookieManager::setCookie( Constants::COOKIE_TARGET_LANG, $this->target_lang,
                [
                        'expires'  => time() + ( 86400 * 365 ),
                        'path'     => '/',
                        'domain'   => INIT::$COOKIE_DOMAIN,
                        'secure'   => true,
                        'httponly' => true,
                        'samesite' => 'None',
                ]
        );

        //search in fileNames if there's a zip file. If it's present, get filenames and add the instead of the zip file.

        $uploadDir  = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $_COOKIE[ 'upload_token' ];
        $newArFiles = [];
        $fs         = FilesStorageFactory::create();

        foreach ( $arFiles as $__fName ) {
            if ( 'zip' == AbstractFilesStorage::pathinfo_fix( $__fName, PATHINFO_EXTENSION ) ) {

                $fs->cacheZipArchive( sha1_file( $uploadDir . DIRECTORY_SEPARATOR . $__fName ), $uploadDir . DIRECTORY_SEPARATOR . $__fName );

                $linkFiles = scandir( $uploadDir );

                //fetch cache links, created by converter, from upload directory
                foreach ( $linkFiles as $storedFileName ) {
                    //check if file begins with the name of the zip file.
                    // If so, then it was stored in the zip file.
                    if ( strpos( $storedFileName, $__fName ) !== false &&
                            substr( $storedFileName, 0, strlen( $__fName ) ) == $__fName ) {
                        //add file name to the files array
                        $newArFiles[] = $storedFileName;
                    }
                }

            } else { //this file was not in a zip. Add it normally

                if ( file_exists( $uploadDir . DIRECTORY_SEPARATOR . $__fName ) ) {
                    $newArFiles[] = $__fName;
                }

            }
        }

        $arFiles = $newArFiles;
        $arMeta  = [];

        // create array_files_meta
        foreach ( $arFiles as $arFile ) {
            $arMeta[] = $this->getFileMetadata( $uploadDir . DIRECTORY_SEPARATOR . $arFile );
        }

        $projectManager = new ProjectManager();

        $projectStructure = $projectManager->getProjectStructure();

        $projectStructure[ 'project_name' ]                 = $this->project_name;
        $projectStructure[ 'private_tm_key' ]               = $this->private_tm_key;
        $projectStructure[ 'uploadToken' ]                  = $_COOKIE[ 'upload_token' ];
        $projectStructure[ 'array_files' ]                  = $arFiles; //list of file name
        $projectStructure[ 'array_files_meta' ]             = $arMeta; //list of file metadata
        $projectStructure[ 'source_language' ]              = $this->source_lang;
        $projectStructure[ 'target_language' ]              = explode( ',', $this->target_lang );
        $projectStructure[ 'job_subject' ]                  = $this->job_subject;
        $projectStructure[ 'mt_engine' ]                    = $this->mt_engine;
        $projectStructure[ 'tms_engine' ]                   = $this->tms_engine;
        $projectStructure[ 'status' ]                       = Constants_ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS;
        $projectStructure[ 'pretranslate_100' ]             = $this->pretranslate_100;
        $projectStructure[ 'pretranslate_101' ]             = $this->pretranslate_101;
        $projectStructure[ 'dialect_strict' ]               = $this->dialect_strict;
        $projectStructure[ 'only_private' ]                 = $this->only_private;
        $projectStructure[ 'due_date' ]                     = $this->due_date;
        $projectStructure[ 'target_language_mt_engine_id' ] = $this->postInput[ 'target_language_mt_engine_id' ];
        $projectStructure[ 'user_ip' ]                      = Utils::getRealIpAddr();
        $projectStructure[ 'HTTP_HOST' ]                    = INIT::$HTTPHOST;

        $projectStructure[ 'dictation' ]         = $this->dictation;
        $projectStructure[ 'show_whitespace' ]   = $this->show_whitespace;
        $projectStructure[ 'character_counter' ] = $this->character_counter;
        $projectStructure[ 'ai_assistant' ]      = $this->ai_assistant;
        $projectStructure[ 'tm_prioritization' ] = $this->tm_prioritization ?? null;

        $projectStructure[ 'character_counter_mode' ] = $this->character_counter_mode ?? null;
        $projectStructure[ 'character_counter_count_tags' ] = $this->character_counter_count_tags ?? null;

        // MMT Glossaries
        // (if $engine is not an MMT instance, ignore 'mmt_glossaries')
        $engine = Engine::getInstance( $this->mt_engine );
        if ( $engine instanceof Engines_MMT and $this->mmt_glossaries !== null ) {
            $projectStructure[ 'mmt_glossaries' ] = $this->mmt_glossaries;
        }

        // DeepL
        if ( $engine instanceof Engines_DeepL and $this->deepl_formality !== null ) {
            $projectStructure[ 'deepl_formality' ] = $this->deepl_formality;
        }

        if ( $engine instanceof Engines_DeepL and $this->deepl_id_glossary !== null ) {
            $projectStructure[ 'deepl_id_glossary' ] = $this->deepl_id_glossary;
        }

        if ( $this->filters_extraction_parameters ) {
            $projectStructure[ 'filters_extraction_parameters' ] = $this->filters_extraction_parameters;
        }

        if ( $this->xliff_parameters ) {
            $projectStructure[ 'xliff_parameters' ] = $this->xliff_parameters;
        }

        // with the qa template id
        if ( $this->qaModelTemplate ) {
            $projectStructure[ 'qa_model_template' ] = $this->qaModelTemplate->getDecodedModel();
        }

        if ( $this->payableRateModelTemplate ) {
            $projectStructure[ 'payable_rate_model_id' ] = $this->payableRateModelTemplate->id;
        }

        //TODO enable from CONFIG
        $projectStructure[ 'metadata' ] = $this->metadata;

        if ( $this->userIsLogged ) {
            $projectStructure[ 'userIsLogged' ] = true;
            $projectStructure[ 'uid' ]          = $this->user->uid;
            $projectStructure[ 'id_customer' ]  = $this->user->email;
            $projectStructure[ 'owner' ]        = $this->user->email;
            $projectManager->setTeam( $this->team ); // set the team object to avoid useless query
        }

        //set features override
        $projectStructure[ 'project_features' ] = $this->projectFeatures;

        //reserve a project id from the sequence
        $projectStructure[ 'id_project' ] = Database::obtain()->nextSequence( Database::SEQ_ID_PROJECT )[ 0 ];
        $projectStructure[ 'ppassword' ]  = $projectManager->generatePassword();

        try {
            $projectManager->sanitizeProjectStructure();
        } catch ( Exception $e ) {
            $this->result[ 'errors' ][] = [
                    "code"    => $e->getCode(),
                    "message" => $e->getMessage()
            ];

            return -1;
        }

        try {
            $fs::moveFileFromUploadSessionToQueuePath( $_COOKIE[ 'upload_token' ] );
        } catch ( Exception $e ) {
            $this->result[ 'errors' ][] = [
                    "code"    => -235, // Error during moving file from upload session folder to queue path
                    "message" => $e->getMessage()
            ];

            return -1;
        }

        Queue::sendProject( $projectStructure );

        $this->__clearSessionFiles();
        $this->__assignLastCreatedPid( $projectStructure[ 'id_project' ] );

        $this->result[ 'data' ] = [
                'id_project' => $projectStructure[ 'id_project' ],
                'password'   => $projectStructure[ 'ppassword' ]
        ];

    }

    /**
     * @param $filename
     *
     * @return array
     * @throws \API\Commons\Exceptions\AuthenticationError
     * @throws \Exceptions\NotFoundException
     * @throws \Exceptions\ValidationError
     * @throws \TaskRunner\Exceptions\EndQueueException
     * @throws \TaskRunner\Exceptions\ReQueueException
     */
    private function getFileMetadata( $filename ) {
        $info          = XliffProprietaryDetect::getInfo( $filename );
        $isXliff       = XliffFiles::isXliff( $filename );
        $isGlossary    = XliffFiles::isGlossaryFile( $filename );
        $isTMX         = XliffFiles::isTMXFile( $filename );
        $getMemoryType = XliffFiles::getMemoryFileType( $filename );

        $forceXliff      = $this->getFeatureSet()->filter(
                'forceXLIFFConversion',
                INIT::$FORCE_XLIFF_CONVERSION,
                $this->userIsLogged,
                $info[ 'info' ][ 'dirname' ] . DIRECTORY_SEPARATOR . "$filename"
        );
        $mustBeConverted = XliffProprietaryDetect::fileMustBeConverted( $filename, $forceXliff, INIT::$FILTERS_ADDRESS );

        $metadata                      = [];
        $metadata[ 'basename' ]        = $info[ 'info' ][ 'basename' ];
        $metadata[ 'dirname' ]         = $info[ 'info' ][ 'dirname' ];
        $metadata[ 'extension' ]       = $info[ 'info' ][ 'extension' ];
        $metadata[ 'filename' ]        = $info[ 'info' ][ 'filename' ];
        $metadata[ 'mustBeConverted' ] = $mustBeConverted;
        $metadata[ 'getMemoryType' ]   = $getMemoryType;
        $metadata[ 'isXliff' ]         = $isXliff;
        $metadata[ 'isGlossary' ]      = $isGlossary;
        $metadata[ 'isTMX' ]           = $isTMX;
        $metadata[ 'proprietary' ]     = [
                'proprietary'            => $info[ 'proprietary' ],
                'proprietary_name'       => $info[ 'proprietary_name' ],
                'proprietary_short_name' => $info[ 'proprietary_short_name' ],
        ];

        return $metadata;
    }

    /**
     * Loads current features from current logged user.
     */
    private function setupUserFeatures() {
        if ( $this->userIsLogged ) {
            $this->featureSet->loadFromUserEmail( $this->user->email );
        }
    }

    private function __addFilterForMetadataInput( $filterArgs ) {
        $filterArgs = array_merge( $filterArgs, [
                'lexiqa'            => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'speech2text'       => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'tag_projection'    => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'segmentation_rule' => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
        ] );

        $filterArgs = $this->featureSet->filter( 'filterCreateProjectInputFilters', $filterArgs );

        return $filterArgs;
    }


    private function __assignLastCreatedPid( $pid ) {
        $_SESSION[ 'redeem_project' ]   = false;
        $_SESSION[ 'last_created_pid' ] = $pid;
    }

    /**
     * @param $tm_keys
     * @throws \Exception
     */
    private function __validateTMKeysArray($tm_keys)
    {
        try {
            $schema = file_get_contents( INIT::$ROOT . '/inc/validation/schema/job_keys.json' );

            $validatorObject       = new JSONValidatorObject();
            $validatorObject->json = $tm_keys;

            $validator = new JSONValidator( $schema );
            $validator->validate( $validatorObject );
        } catch ( Exception $e ) {
            $this->result[ 'errors' ][] = [ "code" => -12, "message" => $e->getMessage() ];
        }
    }

    private function __validateTargetLangs( Languages $lang_handler ) {
        $targets = explode( ',', $this->target_lang );
        $targets = array_map( 'trim', $targets );
        $targets = array_unique( $targets );

        if ( empty( $targets ) ) {
            $this->result[ 'errors' ][] = [ "code" => -4, "message" => "Missing target language." ];
        }

        try {
            foreach ( $targets as $target ) {
                $lang_handler->validateLanguage( $target );
            }
        } catch ( Exception $e ) {
            $this->result[ 'errors' ][] = [ "code" => -4, "message" => $e->getMessage() ];
        }

        $this->target_lang = implode( ',', $targets );
    }

    private function __validateSourceLang( Languages $lang_handler ) {
        try {
            $lang_handler->validateLanguage( $this->source_lang );
        } catch ( Exception $e ) {
            $this->result[ 'errors' ][] = [ "code" => -3, "message" => $e->getMessage() ];
        }
    }

    private function __clearSessionFiles() {

        if ( $this->userIsLogged ) {
            $gdriveSession = new Session();
            $gdriveSession->clearFileListFromSession();
        }
    }

    private static function sanitizeTmKeyArr( $elem ) {

        $element                  = new TmKeyManagement_TmKeyStruct( $elem );
        $element->complete_format = true;
        $elem                     = TmKeyManagement_TmKeyManagement::sanitize( $element );

        return $elem->toArray();

    }

    /**
     * This function sets metadata property from input params.
     *
     */
    private function __setMetadataFromPostInput() {

        // new raw counter model
        $options = [ Projects_MetadataDao::WORD_COUNT_TYPE_KEY => Projects_MetadataDao::WORD_COUNT_RAW ];

        if ( isset( $this->postInput[ 'lexiqa' ] ) ) {
            $options[ 'lexiqa' ] = $this->postInput[ 'lexiqa' ];
        }
        if ( isset( $this->postInput[ 'speech2text' ] ) ) {
            $options[ 'speech2text' ] = $this->postInput[ 'speech2text' ];
        }
        if ( isset( $this->postInput[ 'tag_projection' ] ) ) {
            $options[ 'tag_projection' ] = $this->postInput[ 'tag_projection' ];
        }
        if ( isset( $this->postInput[ 'segmentation_rule' ] ) ) {
            $options[ 'segmentation_rule' ] = $this->postInput[ 'segmentation_rule' ];
        }

        $this->metadata = $options;

        $this->metadata = $this->featureSet->filter( 'createProjectAssignInputMetadata', $this->metadata, [
                'input' => $this->postInput
        ] );
    }

    /**
     * TODO: this should be moved to a model that.
     *
     * @param null $id_team
     *
     * @throws Exception
     */
    private function __setTeam( $id_team = null ) {
        if ( is_null( $id_team ) ) {
            $this->team = $this->user->getPersonalTeam();
        } else {
            // check for the team to be allowed
            $dao  = new \Teams\MembershipDao();
            $team = $dao->findTeamByIdAndUser( $id_team, $this->user );

            if ( !$team ) {
                throw new Exception( 'Team and user memberships do not match' );
            } else {
                $this->team = $team;
            }
        }
    }

    /**
     * @throws Exception
     */
    private function __validateCharacterCounterMode() {
        if ( !empty( $this->character_counter_mode ) ) {
            $allowed = [
                "google_ads",
                "exclude_cjk",
                "all_one"
            ];

            if(!in_array($this->postInput[ 'character_counter_mode' ], $allowed)){
                $this->result[ 'errors' ][] = [ "code" => -2, "message" => "Invalid character counter mode." ];
            }
        }
    }

    /**
     * Check if MT engine (except MyMemory) belongs to user
     */
    private function __validateUserMTEngine() {

        if ( $this->mt_engine > 1 and $this->isLoggedIn() ) {
            try {
                EngineValidator::engineBelongsToUser( $this->mt_engine, $this->user->uid );
            } catch ( Exception $exception ) {
                $this->result[ 'errors' ][] = [ "code" => -2, "message" => $exception->getMessage() ];
            }
        }
    }

    /**
     * Validate `mmt_glossaries` string
     */
    private function __validateMMTGlossaries() {

        if ( !empty( $this->postInput[ 'mmt_glossaries' ] ) and $this->isLoggedIn() ) {
            try {
                $mmtGlossaries = html_entity_decode( $this->postInput[ 'mmt_glossaries' ] );
                MMTValidator::validateGlossary( $mmtGlossaries );

                $this->mmt_glossaries = $mmtGlossaries;

            } catch ( Exception $exception ) {
                $this->result[ 'errors' ][] = [ "code" => -6, "message" => $exception->getMessage() ];
            }
        }
    }

    /**
     * Validate DeepL params
     */
    private function __validateDeepLGlossaryParams() {

        if ( $this->isLoggedIn() ) {

            if ( !empty( $this->postInput[ 'deepl_formality' ] ) ) {

                $allowedFormalities = [
                        'default',
                        'prefer_less',
                        'prefer_more'
                ];

                if ( in_array( $this->postInput[ 'deepl_formality' ], $allowedFormalities ) ) {
                    $this->deepl_formality = $this->postInput[ 'deepl_formality' ];
                }
            }

            if ( !empty( $this->postInput[ 'deepl_id_glossary' ] ) ) {
                $this->deepl_id_glossary = $this->postInput[ 'deepl_id_glossary' ];
            }
        }
    }

    /**
     * @throws Exception
     */
    private function __validateQaModelTemplate() {
        if ( !empty( $this->postInput[ 'qa_model_template_id' ] ) and $this->postInput[ 'qa_model_template_id' ] > 0 ) {
            $qaModelTemplate = QAModelTemplateDao::get( [
                    'id'  => $this->postInput[ 'qa_model_template_id' ],
                    'uid' => $this->getUser()->uid
            ] );

            // check if qa_model template exists
            if ( null === $qaModelTemplate ) {
                throw new Exception( 'This QA Model template does not exists or does not belongs to the logged in user' );
            }

            $this->qaModelTemplate = $qaModelTemplate;
        }
    }

    /**
     * @throws Exception
     */
    private function __validatePayableRateTemplate() {
        $payableRateModelTemplate = null;

        if ( !empty( $this->postInput[ 'payable_rate_template_id' ] ) and $this->postInput[ 'payable_rate_template_id' ] > 0 ) {

            $payableRateTemplateId = $this->postInput[ 'payable_rate_template_id' ];
            $userId                = $this->getUser()->uid;

            $payableRateModelTemplate = CustomPayableRateDao::getByIdAndUser( $payableRateTemplateId, $userId );

            if ( null === $payableRateModelTemplate ) {
                throw new Exception( 'Payable rate model id not valid' );
            }

        }

        $this->payableRateModelTemplate = $payableRateModelTemplate;
    }

    /**
     * Validate `dialect_strict` param vs target languages
     *
     * Example: {"it-IT": true, "en-US": false, "fr-FR": false}
     *
     * @throws Exception
     */
    private function __validateDialectStrictParam() {
        if ( !empty( $this->postInput[ 'dialect_strict' ] ) ) {
            $dialect_strict   = trim( html_entity_decode( $this->postInput[ 'dialect_strict' ] ) );
            $target_languages = preg_replace( '/\s+/', '', $this->postInput[ 'target_lang' ] );
            $targets          = explode( ',', trim( $target_languages ) );
            $dialectStrictObj = json_decode( $dialect_strict, true );

            foreach ( $dialectStrictObj as $lang => $value ) {
                if ( !in_array( $lang, $targets ) ) {
                    throw new Exception( 'Wrong `dialect_strict` object, language, ' . $lang . ' is not one of the project target languages' );
                }

                if ( !is_bool( $value ) ) {
                    throw new Exception( 'Wrong `dialect_strict` object, not boolean declared value for ' . $lang );
                }
            }

            $this->dialect_strict = html_entity_decode( $dialect_strict );
        }
    }

    /**
     * @throws Exception
     */
    private function __validateFiltersExtractionParameters() {
        if ( !empty( $this->postInput[ 'filters_extraction_parameters' ] ) ) {

            $json   = html_entity_decode( $this->postInput[ 'filters_extraction_parameters' ] );
            $schema = file_get_contents( INIT::$ROOT . '/inc/validation/schema/filters_extraction_parameters.json' );

            $validatorObject       = new JSONValidatorObject();
            $validatorObject->json = $json;

            $validator = new JSONValidator( $schema );
            $validator->validate( $validatorObject );

            $this->filters_extraction_parameters = json_decode( $json );
        }
    }

    /**
     * @throws Exception
     */
    private function __validateXliffParameters() {

        if ( !empty( $this->postInput[ 'xliff_parameters' ] ) ) {

            $json   = html_entity_decode( $this->postInput[ 'xliff_parameters' ] );

            // first check if `xliff_parameters` is a valid JSON
            if ( !Utils::isJson( $json ) ) {
                throw new Exception( "xliff_parameters is not a valid JSON" );
            }

            $schema = file_get_contents( INIT::$ROOT . '/inc/validation/schema/xliff_parameters_rules_content.json' );

            $validatorObject       = new JSONValidatorObject();
            $validatorObject->json = $json;

            $validator = new JSONValidator( $schema, true );
            $validator->validate( $validatorObject );
            $this->xliff_parameters = json_decode( $json, true ); // decode again because we need an associative array and not stdClass

        } elseif ( !empty( $this->postInput[ 'xliff_parameters_template_id' ] ) ) {

            $xliffConfigTemplate = XliffConfigTemplateDao::getByIdAndUser( $this->postInput[ 'xliff_parameters_template_id' ], $this->getUser()->uid );

            if ( $xliffConfigTemplate === null ) {
                throw new Exception( "xliff_parameters_template_id not valid" );
            }

            $this->xliff_parameters = $xliffConfigTemplate->rules->getArrayCopy();

        }

    }

    /**
     * This could be already set by MMT engine if enabled ( so check key existence and do not override )
     *
     * @see filterCreateProjectFeatures callback
     * @see createProjectController::__appendFeaturesToProject()
     */
    private function __generateTargetEngineAssociation() {
        if ( !isset( $this->postInput[ 'target_language_mt_engine_id' ] ) ) { // this could be already set by MMT engine if enabled ( so check and do not override )
            foreach ( explode( ",", $this->target_lang ) as $_matecatTarget ) {
                $this->postInput[ 'target_language_mt_engine_id' ][ $_matecatTarget ] = $this->mt_engine;
            }
        }
    }

}

