<?php

namespace Controller\API\V1;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\ScanDirectoryForConvertedFiles;
use Exception;
use InvalidArgumentException;
use Model\Conversion\FilesConverter;
use Model\Conversion\Upload;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FilesStorage\AbstractFilesStorage;
use Model\FilesStorage\FilesStorageFactory;
use Model\Filters\FiltersConfigTemplateDao;
use Model\Filters\FiltersConfigTemplateStruct;
use Model\LQA\ModelDao;
use Model\LQA\ModelStruct;
use Model\LQA\QAModelTemplate\QAModelTemplateDao;
use Model\LQA\QAModelTemplate\QAModelTemplateStruct;
use Model\MTQE\PayableRate\DTO\MTQEPayableRateBreakdowns;
use Model\MTQE\PayableRate\MTQEPayableRateTemplateDao;
use Model\MTQE\Templates\DTO\MTQEWorkflowParams;
use Model\MTQE\Templates\MTQEWorkflowTemplateDao;
use Model\PayableRates\CustomPayableRateDao;
use Model\PayableRates\CustomPayableRateStruct;
use Model\ProjectManager\ProjectManager;
use Model\Projects\MetadataDao;
use Model\Teams\MembershipDao;
use Model\Teams\TeamStruct;
use Model\TmKeyManagement\MemoryKeyDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Xliff\XliffConfigTemplateDao;
use Plugins\Features\ProjectCompletion;
use RuntimeException;
use SebastianBergmann\Invoker\TimeoutException;
use Utils\ActiveMQ\ClientHelpers\ProjectQueue;
use Utils\Constants\Constants;
use Utils\Constants\ProjectStatus;
use Utils\Constants\TmKeyPermissions;
use Utils\Engines\DeepL;
use Utils\Engines\EnginesFactory;
use Utils\Langs\LanguageDomains;
use Utils\Langs\Languages;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;
use Utils\TmKeyManagement\TmKeyManager;
use Utils\TmKeyManagement\TmKeyStruct;
use Utils\TMS\TMSService;
use Utils\Tools\Utils;
use Utils\Validator\Contracts\ValidatorObject;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;
use Utils\Validator\MMTValidator;

class NewController extends KleinController {

    use ScanDirectoryForConvertedFiles;

    const MAX_NUM_KEYS = 13;

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @throws AuthenticationError
     * @throws ReQueueException
     * @throws ValidationError
     * @throws NotFoundException
     * @throws EndQueueException
     * @throws Exception
     */
    public function create(): void {

        $this->featureSet->loadFromUserEmail( $this->user->email );
        $request    = $this->validateTheRequest();
        $fs         = FilesStorageFactory::create();
        $uploadFile = new Upload();

        $stdResult = $uploadFile->uploadFiles( $_FILES );

        $arFiles = [];

        foreach ( $stdResult as $input_value ) {
            $arFiles[] = $input_value->name;
        }

        // if fileupload was failed, this index (0 = does not exist)
        $default_project_name = @$arFiles[ 0 ];
        if ( count( $arFiles ) > 1 ) {
            $default_project_name = "MATECAT_PROJ-" . date( "Ymdhi" );
        }

        if ( empty( $request[ 'project_name' ] ) ) {
            $request[ 'project_name' ] = $default_project_name; //'NO_NAME'.$this->create_project_name();
        }

        $uploadTokenValue = $uploadFile->getDirUploadToken();
        $uploadDir        = AppConfig::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $uploadTokenValue;
        $errDir           = AppConfig::$STORAGE_DIR . DIRECTORY_SEPARATOR . 'conversion_errors' . DIRECTORY_SEPARATOR . $uploadTokenValue;

        $converter = new FilesConverter(
                $arFiles,
                $request[ 'source_lang' ],
                $request[ 'target_lang' ],
                $uploadDir,
                $errDir,
                $uploadTokenValue,
                $request[ 'segmentation_rule' ],
                $this->featureSet,
                $request[ 'filters_extraction_parameters' ],
                $request[ 'legacy_icu' ],
        );

        $converter->convertFiles();

        $result      = $converter->getResult();
        $errorStatus = [];
        if ( $result->hasErrors() ) {
            $errorStatus = $result->getErrors();
        }

        // Upload errors handling
        if ( !empty( $errorStatus ) ) {
            $this->response->code( 400 );
            $this->response->json( [ 'status' => 'KO', 'errors' => $errorStatus ] );

            return;
        }

        $result = $result->getData();

        $filesFound = $this->getFilesList( FilesStorageFactory::create(), $arFiles, $uploadDir );

        $projectManager                                 = new ProjectManager();
        $projectStructure                               = $projectManager->getProjectStructure();
        $projectStructure[ 'sanitize_project_options' ] = false;
        $projectStructure[ 'project_name' ]             = $request[ 'project_name' ];
        $projectStructure[ 'job_subject' ]              = $request[ 'subject' ];
        $projectStructure[ 'private_tm_key' ]           = $request[ 'private_tm_key' ];
        $projectStructure[ 'private_tm_user' ]          = $request[ 'private_tm_user' ];
        $projectStructure[ 'private_tm_pass' ]          = $request[ 'private_tm_pass' ];
        $projectStructure[ 'tm_prioritization' ]        = $request[ 'tm_prioritization' ];
        $projectStructure[ 'uploadToken' ]              = $uploadFile->getDirUploadToken();
        $projectStructure[ 'array_files' ]              = $filesFound[ 'arrayFiles' ]; //list of file names
        $projectStructure[ 'array_files_meta' ]         = $filesFound[ 'arrayFilesMeta' ]; //list of file metadata
        $projectStructure[ 'source_language' ]          = $request[ 'source_lang' ];
        $projectStructure[ 'target_language' ]          = explode( ',', $request[ 'target_lang' ] );
        $projectStructure[ 'mt_engine' ]                = $request[ 'mt_engine' ];
        $projectStructure[ 'tms_engine' ]               = $request[ 'tms_engine' ];
        $projectStructure[ 'status' ]                   = ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS;
        $projectStructure[ 'owner' ]                    = $this->user->email;
        $projectStructure[ 'metadata' ]                 = $request[ 'metadata' ];
        $projectStructure[ 'public_tm_penalty' ]        = $request[ 'public_tm_penalty' ];
        $projectStructure[ 'pretranslate_100' ]         = (int)!!$request[ 'pretranslate_100' ]; // Force pretranslate_100 to be 0 or 1
        $projectStructure[ 'pretranslate_101' ]         = isset( $request[ 'pretranslate_101' ] ) ? (int)$request[ 'pretranslate_101' ] : 1;

        //default gets all public matches from TM
        $projectStructure[ 'only_private' ] = ( isset( $request[ 'get_public_matches' ] ) && !$request[ 'get_public_matches' ] );

        $projectStructure[ 'user_ip' ]                               = Utils::getRealIpAddr();
        $projectStructure[ 'HTTP_HOST' ]                             = AppConfig::$HTTPHOST;
        $projectStructure[ 'due_date' ]                              = ( empty( $request[ 'due_date' ] ) ? null : Utils::mysqlTimestamp( $request[ 'due_date' ] ) );
        $projectStructure[ 'target_language_mt_engine_association' ] = $request[ 'target_language_mt_engine_association' ];
        $projectStructure[ 'instructions' ]                          = $request[ 'instructions' ];

        $projectStructure[ 'userIsLogged' ] = true;
        $projectStructure[ 'uid' ]          = $this->user->getUid();
        $projectStructure[ 'id_customer' ]  = $this->user->getEmail();
        $projectManager->setTeam( $request[ 'team' ] );

        $projectStructure[ 'character_counter_mode' ]       = ( !empty( $request[ 'character_counter_mode' ] ) ) ? $request[ 'character_counter_mode' ] : null;
        $projectStructure[ 'character_counter_count_tags' ] = ( !empty( $request[ 'character_counter_count_tags' ] ) ) ? $request[ 'character_counter_count_tags' ] : null;

        // Lara glossaries
        if ( $request[ 'lara_glossaries' ] ) {
            $projectStructure[ 'lara_glossaries' ] = $request[ 'lara_glossaries' ];
        }

        // mmtGlossaries
        if ( $request[ 'mmt_glossaries' ] ) {
            $projectStructure[ 'mmt_glossaries' ] = $request[ 'mmt_glossaries' ];
        }

        // DeepL
        $engine = EnginesFactory::getInstance( $request[ 'mt_engine' ] );
        if ( $engine instanceof DeepL and $request[ 'deepl_formality' ] !== null ) {
            $projectStructure[ 'deepl_formality' ] = $request[ 'deepl_formality' ];
        }

        if ( $engine instanceof DeepL and $request[ 'deepl_id_glossary' ] !== null ) {
            $projectStructure[ 'deepl_id_glossary' ] = $request[ 'deepl_id_glossary' ];
        }

        // with the qa template id
        if ( $request[ 'qaModelTemplate' ] ) {
            $projectStructure[ 'qa_model_template' ] = $request[ 'qaModelTemplate' ]->getDecodedModel();
        }

        if ( $request[ 'qaModel' ] ) {
            $projectStructure[ 'qa_model' ] = $request[ 'qaModel' ]->getDecodedModel();
        }

        if ( $request[ 'mt_qe_workflow_payable_rate' ] ) {
            $projectStructure[ 'mt_qe_workflow_payable_rate' ] = $request[ 'mt_qe_workflow_payable_rate' ];
        }

        if ( $request[ 'payableRateModelTemplate' ] ) {
            $projectStructure[ 'payable_rate_model_id' ] = $request[ 'payableRateModelTemplate' ]->id;
        }

        if ( $request[ 'dialect_strict' ] ) {
            $projectStructure[ 'dialect_strict' ] = $request[ 'dialect_strict' ];
        }

        if ( $request[ 'filters_extraction_parameters' ] ) {
            $projectStructure[ 'filters_extraction_parameters' ] = $request[ 'filters_extraction_parameters' ];
        }

        if ( $request[ 'xliff_parameters' ] ) {
            $projectStructure[ 'xliff_parameters' ] = $request[ 'xliff_parameters' ];
        }

        if ( $request[ 'mt_evaluation' ] ) {
            $projectStructure[ 'mt_evaluation' ] = true;
        }

        //set features override
        $projectStructure[ 'project_features' ] = $request[ 'project_features' ];

        $projectManager->sanitizeProjectStructure();

        $fs::moveFileFromUploadSessionToQueuePath( $uploadFile->getDirUploadToken() );

        //reserve a project id from the sequence
        $projectStructure[ 'id_project' ] = Database::obtain()->nextSequence( Database::SEQ_ID_PROJECT )[ 0 ];
        $projectStructure[ 'ppassword' ]  = $projectManager->generatePassword();

        $projectStructure = $this->featureSet->filter( 'addNewProjectStructureAttributes', $projectStructure, $_POST );

        // flag to mark the project "from API"
        $projectStructure[ 'from_api' ] = true;

        ProjectQueue::sendProject( $projectStructure );

        $result[ 'errors' ] = $this->pollForCreationResult( $projectStructure );

        if ( $result == null ) {
            throw new TimeoutException( 'Project Creation Failure' );
        }

        if ( !empty( $result[ 'errors' ] ) ) {
            throw new RuntimeException( 'Project Creation Failure' );
        }

        $this->response->json( [
                'status'       => 'OK',
                'message'      => 'Success',
                'id_project'   => $projectStructure[ 'id_project' ],
                'project_pass' => $projectStructure[ 'ppassword' ],
                'new_keys'     => $request[ 'new_keys' ],
                'analyze_url'  => $projectManager->getAnalyzeURL()
        ] );

    }

    /**
     * @param $projectStructure
     *
     * @return array
     */
    private function pollForCreationResult( $projectStructure ): array {
        return $projectStructure[ 'result' ][ 'errors' ]->getArrayCopy();
    }

    /**
     * @return void
     * @throws Exception
     */
    private function validateTheRequest(): array {
        $character_counter_count_tags              = filter_var( $this->request->param( 'character_counter_count_tags' ), FILTER_VALIDATE_BOOLEAN );
        $character_counter_mode                    = filter_var( $this->request->param( 'character_counter_mode' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW ] );
        $due_date                                  = filter_var( $this->request->param( 'due_date' ), FILTER_SANITIZE_NUMBER_INT );
        $deepl_formality                           = filter_var( $this->request->param( 'deepl_formality' ), FILTER_SANITIZE_STRING );
        $deepl_id_glossary                         = filter_var( $this->request->param( 'deepl_id_glossary' ), FILTER_SANITIZE_STRING );
        $dialect_strict                            = filter_var( $this->request->param( 'dialect_strict' ), FILTER_SANITIZE_STRING );
        $filters_extraction_parameters             = filter_var( $this->request->param( 'filters_extraction_parameters' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_NO_ENCODE_QUOTES ] );
        $filters_extraction_parameters_template_id = filter_var( $this->request->param( 'filters_extraction_parameters_template_id' ), FILTER_SANITIZE_NUMBER_INT );
        $get_public_matches                        = (bool)filter_var( $this->request->param( 'get_public_matches' ), FILTER_SANITIZE_NUMBER_INT, [ 'filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => [ 'default' => 1, 'min_range' => 0, 'max_range' => 1 ] ] ); // used to set the default value of get_public_matches to 1
        $id_qa_model                               = filter_var( $this->request->param( 'id_qa_model' ), FILTER_SANITIZE_NUMBER_INT );
        $id_qa_model_template                      = filter_var( $this->request->param( 'id_qa_model_template' ), FILTER_SANITIZE_NUMBER_INT );
        $id_team                                   = filter_var( $this->request->param( 'id_team' ), FILTER_SANITIZE_NUMBER_INT, [ 'flags' => FILTER_REQUIRE_SCALAR ] );
        $metadata                                  = filter_var( $this->request->param( 'metadata' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $mmt_glossaries                            = filter_var( $this->request->param( 'mmt_glossaries' ), FILTER_SANITIZE_STRING );
        $lara_glossaries                           = filter_var( $this->request->param( 'lara_glossaries' ), FILTER_SANITIZE_STRING );
        $mt_engine                                 = filter_var( $this->request->param( 'mt_engine' ), FILTER_SANITIZE_NUMBER_INT, [ 'filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => [ 'default' => 1, 'min_range' => 0 ] ] );
        $mt_evaluation                             = filter_var( $this->request->param( 'mt_evaluation' ), FILTER_VALIDATE_BOOLEAN );
        $mt_quality_value_in_editor                = filter_var( $this->request->param( 'mt_quality_value_in_editor' ), FILTER_SANITIZE_NUMBER_INT, [ 'filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => [ 'default' => 86, 'min_range' => 76, 'max_range' => 102 ] ] ); // used to set the absolute value of an MT match (previously fixed to 85)
        $legacy_icu                                = filter_var( $this->request->param( 'legacy_icu' ), FILTER_VALIDATE_BOOLEAN );
        $mt_qe_workflow_enable                     = filter_var( $this->request->param( 'mt_qe_workflow_enable' ), FILTER_VALIDATE_BOOLEAN );
        $mt_qe_workflow_template_id                = filter_var( $this->request->param( 'mt_qe_workflow_qe_model_id' ), FILTER_SANITIZE_NUMBER_INT ) ?: null;         // QE workflow parameters
        $mt_qe_workflow_template_raw_parameters    = filter_var( $this->request->param( 'mt_qe_workflow_template_raw_parameters' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_NO_ENCODE_QUOTES | FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] ) ?: null;  // QE workflow parameters in raw string JSON format
        $mt_qe_workflow_payable_rate_template_id   = filter_var( $this->request->param( 'mt_qe_workflow_payable_rate_template_id' ), FILTER_SANITIZE_NUMBER_INT ) ?: null;         // QE workflow parameters
        $payable_rate_template_id                  = filter_var( $this->request->param( 'payable_rate_template_id' ), FILTER_SANITIZE_NUMBER_INT );
        $payable_rate_template_name                = filter_var( $this->request->param( 'payable_rate_template_name' ), FILTER_SANITIZE_STRING );
        $project_info                              = filter_var( $this->request->param( 'project_info' ), FILTER_SANITIZE_STRING );
        $project_name                              = filter_var( $this->request->param( 'project_name' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $public_tm_penalty                         = filter_var( $this->request->param( 'public_tm_penalty' ), FILTER_SANITIZE_NUMBER_INT );
        $pretranslate_100                          = filter_var( $this->request->param( 'pretranslate_100' ), FILTER_VALIDATE_BOOLEAN );
        $pretranslate_101                          = filter_var( $this->request->param( 'pretranslate_101' ), FILTER_VALIDATE_BOOLEAN );
        $private_tm_key                            = filter_var( $this->request->param( 'private_tm_key' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $private_tm_key_json                       = filter_var( $this->request->param( 'private_tm_key_json' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_NO_ENCODE_QUOTES ] );
        $project_completion                        = filter_var( $this->request->param( 'project_completion' ), FILTER_VALIDATE_BOOLEAN );
        $qa_model_template_id                      = filter_var( $this->request->param( 'qa_model_template_id' ), FILTER_SANITIZE_NUMBER_INT );
        $segmentation_rule                         = filter_var( $this->request->param( 'segmentation_rule' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $source_lang                               = filter_var( $this->request->param( 'source_lang' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $speech2text                               = filter_var( $this->request->param( 'speech2text' ), FILTER_VALIDATE_BOOLEAN );
        $subject                                   = filter_var( $this->request->param( 'subject' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $target_lang                               = filter_var( $this->request->param( 'target_lang' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $tms_engine                                = filter_var( $this->request->param( 'tms_engine' ), FILTER_VALIDATE_INT, [ 'filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR, 'options' => [ 'default' => 1, 'min_range' => 0 ] ] );
        $xliff_parameters                          = filter_var( $this->request->param( 'xliff_parameters' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_NO_ENCODE_QUOTES ] );
        $xliff_parameters_template_id              = filter_var( $this->request->param( 'xliff_parameters_template_id' ), FILTER_SANITIZE_NUMBER_INT );

        // Strip tags from instructions
        $instructions = [];
        if ( is_array( $this->request->param( 'instructions' ) ) ) {
            /** @var array $instructions */
            $instructions = $this->request->param( 'instructions' );
            foreach ( $instructions as $value ) {
                $instructions[] = Utils::stripTagsPreservingHrefs( $value );
            }

            /**
             * Uber plugin callback
             */
            $instructions = $this->featureSet->filter( 'encodeInstructions', $instructions ?? null );
        }

        if ( empty( $_FILES ) ) {
            throw new InvalidArgumentException( "Missing file. Not Sent." );
        }

        $lang_handler = Languages::getInstance();

        if ( !empty( $public_tm_penalty ) ) {
            $public_tm_penalty = $this->validatePublicTMPenalty( (int)$public_tm_penalty );
        }

        $source_lang = $this->validateSourceLang( $lang_handler, $source_lang );
        $target_lang = $this->validateTargetLangs( $lang_handler, $target_lang );
        [ $tms_engine, $mt_engine ] = $this->validateEngines( $tms_engine, $mt_engine );
        $subject           = $this->validateSubject( $subject );
        $segmentation_rule = $this->validateSegmentationRules( $segmentation_rule );
        [ $private_tm_user, $private_tm_pass, $private_tm_key, $new_keys, $tm_prioritization ] = $this->validateTmAndKeys( $private_tm_key, $private_tm_key_json );
        $team                                  = $this->validateTeam( $id_team );
        $qaModelTemplate                       = $this->validateQaModelTemplate( $id_qa_model_template );
        $payableRateModelTemplate              = $this->validatePayableRateTemplate( $payable_rate_template_name, $payable_rate_template_id );
        $qaModel                               = $this->validateQaModel( $id_qa_model );
        $mt_engine                             = $this->validateUserMTEngine( $mt_engine );
        $mmt_glossaries                        = $this->validateMMTGlossaries( $mmt_glossaries );
        $deepl_formality                       = $this->validateDeepLFormality( $deepl_formality );
        $dialect_strict                        = $this->validateDialectStrictParam( $target_lang, $dialect_strict );
        $filters_extraction_parameters         = $this->validateFiltersExtractionParameters( $filters_extraction_parameters, $filters_extraction_parameters_template_id );
        $xliff_parameters                      = $this->validateXliffParameters( $xliff_parameters, $xliff_parameters_template_id );
        $metadata                              = $this->validateMetadataParam( $metadata );
        $character_counter_mode                = $this->validateCharacterCounterMode( $character_counter_mode );
        $project_features                      = $this->appendFeaturesToProject( (bool)$project_completion, $mt_engine );
        $target_language_mt_engine_association = $this->generateTargetEngineAssociation( $target_lang, $mt_engine );

        if ( $mt_qe_workflow_enable ) {

            // engines restrictions
            if ( $mt_engine <= 1 ) {
                throw new InvalidArgumentException( "MT Engine id $mt_engine is not supported for QE Workflows" );
            }

            $metadata[ MetadataDao::MT_QE_WORKFLOW_ENABLED ]    = $mt_qe_workflow_enable;
            $metadata[ MetadataDao::MT_QE_WORKFLOW_PARAMETERS ] = $this->validateMTQEParametersOrDefault( $mt_qe_workflow_template_id, $mt_qe_workflow_template_raw_parameters ); // or default
            // does not put this in the options, we do not want to save it in the DB as metadata
            $mt_qe_PayableRate = $this->validateMTQEPayableRateBreakdownsOrDefault( $mt_qe_workflow_payable_rate_template_id );
            $mt_evaluation     = true; // force mt_evaluation because it is the default for mt_qe_workflows
        }

        if ( !empty( $project_info ) ) {
            $metadata[ 'project_info' ] = $project_info;
        }

        if ( !empty( $dialect_strict ) ) {
            $metadata[ 'dialect_strict' ] = $dialect_strict;
        }

        if ( !empty( $speech2text ) ) {
            $metadata[ 'speech2text' ] = $speech2text;
        }

        if ( !empty( $project_completion ) ) {
            $metadata[ 'project_completion' ] = $project_completion;
        }

        if ( !empty( $segmentation_rule ) ) {
            $metadata[ 'segmentation_rule' ] = $segmentation_rule;
        }

        $metadata[ MetadataDao::MT_QUALITY_VALUE_IN_EDITOR ] = $mt_quality_value_in_editor;

        if ( $mt_evaluation ) {
            $metadata[ MetadataDao::MT_EVALUATION ] = true;
        }

        return [
                'project_info'                              => $project_info,
                'project_name'                              => $project_name,
                'source_lang'                               => $source_lang,
                'target_lang'                               => $target_lang,
                'subject'                                   => $subject,
                'public_tm_penalty'                         => $public_tm_penalty,
                'pretranslate_100'                          => $pretranslate_100,
                'pretranslate_101'                          => $pretranslate_101,
                'id_team'                                   => $id_team,
                'team'                                      => $team,
                'mmt_glossaries'                            => $mmt_glossaries,
                'lara_glossaries'                           => $lara_glossaries,
                'deepl_id_glossary'                         => $deepl_id_glossary,
                'deepl_formality'                           => $deepl_formality,
                'project_completion'                        => $project_completion,
                'get_public_matches'                        => $get_public_matches,
                'dialect_strict'                            => $dialect_strict,
                'filters_extraction_parameters'             => $filters_extraction_parameters,
                'xliff_parameters'                          => $xliff_parameters,
                'filters_extraction_parameters_template_id' => $filters_extraction_parameters_template_id,
                'qa_model_template_id'                      => $qa_model_template_id,
                'payable_rate_template_id'                  => $payable_rate_template_id,
                'tms_engine'                                => $tms_engine,
                'mt_engine'                                 => $mt_engine,
                'private_tm_key'                            => $private_tm_key,
                'private_tm_user'                           => $private_tm_user,
                'private_tm_pass'                           => $private_tm_pass,
                'tm_prioritization'                         => $tm_prioritization,
                'new_keys'                                  => $new_keys,
                'due_date'                                  => $due_date,
                'id_qa_model'                               => $id_qa_model,
                'qaModel'                                   => $qaModel,
                'metadata'                                  => $metadata,
                'segmentation_rule'                         => $segmentation_rule,
                'id_qa_model_template'                      => $id_qa_model_template,
                'qaModelTemplate'                           => $qaModelTemplate,
                'payableRateModelTemplate'                  => $payableRateModelTemplate,
                'instructions'                              => $instructions,
                'speech2text'                               => $speech2text,
                'project_features'                          => $project_features,
                'mt_evaluation'                             => $mt_evaluation,
                'character_counter_count_tags'              => $character_counter_count_tags,
                'character_counter_mode'                    => $character_counter_mode,
                'target_language_mt_engine_association'     => $target_language_mt_engine_association,
                'mt_qe_workflow_payable_rate'               => $mt_qe_PayableRate ?? null,
                'legacy_icu'                                => $legacy_icu,
        ];
    }

    /**
     * Expects the metadata param to be a JSON formatted string and tries to convert it
     * in an array.
     * JSON string is expected to be a flat key value, this is enforced padding 1 to JSON
     * conversion depth param.
     *
     *
     * @throws Exception
     */
    private function validateMetadataParam( $metadata = null ) {
        if ( !empty( $metadata ) ) {

            if ( strlen( $metadata ) > 2048 ) {
                throw new InvalidArgumentException( 'metadata string is too long' );
            }

            $depth          = 2; // only converts key value structures
            $metadata       = html_entity_decode( $metadata );
            $parsedMetadata = json_decode( $metadata, true, $depth );

            if ( is_array( $parsedMetadata ) ) {
                $metadata = $parsedMetadata;
            }

        } else {
            $metadata = [];
        }

        // new raw counter model
        $metadata[ MetadataDao::WORD_COUNT_TYPE_KEY ] = MetadataDao::WORD_COUNT_RAW;

        return $metadata;

    }

    /**
     * @param string|null $character_counter_mode
     *
     * @return string|null
     */
    private function validateCharacterCounterMode( ?string $character_counter_mode = null ): ?string {

        if ( empty( $character_counter_mode ) ) {
            return null;
        }

        $allowedModes = [
                'google_ads',
                'exclude_cjk',
                'all_one'
        ];

        if ( !in_array( $character_counter_mode, $allowedModes ) ) {
            throw new InvalidArgumentException( "Invalid character counter mode. Allowed values: [google_ads, exclude_cjk, all_one]", -23 );
        }

        return $character_counter_mode;
    }

    /**
     * @param int $tms_engine
     * @param int $mt_engine
     *
     * @return array
     * @throws Exception
     */
    private function validateEngines( int $tms_engine, int $mt_engine ): array {

        if ( $tms_engine > 1 ) {
            throw new InvalidArgumentException( "Invalid TM Engine.", -21 );
        }

        if ( $mt_engine > 1 ) {

            if ( !$this->userIsLogged ) {
                throw new InvalidArgumentException( "Invalid MT Engine.", -2 );
            }

            try {
                EnginesFactory::getInstanceByIdAndUser( $mt_engine, $this->user->uid );
            } catch ( Exception $exception ) {
                throw new InvalidArgumentException( $exception->getMessage(), -2 );
            }

        }

        return [ $tms_engine, $mt_engine ];
    }

    /**
     * @param $subject
     *
     * @return string
     * @throws InvalidArgumentException
     */
    private function validateSubject( $subject ): string {
        $langDomains = LanguageDomains::getInstance();
        $subjectMap  = $langDomains::getEnabledHashMap();

        $subject = ( !empty( $subject ) ) ? $subject : 'general';

        if ( empty( $subjectMap[ $subject ] ) ) {
            throw new InvalidArgumentException( "Subject not allowed: " . $subject, -3 );
        }

        return $subject;
    }

    /**
     * @param int|null $public_tm_penalty
     *
     * @return int|null
     */
    private function validatePublicTMPenalty( ?int $public_tm_penalty = null ): ?int {
        if ( $public_tm_penalty < 0 || $public_tm_penalty > 100 ) {
            throw new InvalidArgumentException( "Invalid public_tm_penalty value (must be between 0 and 100)", -6 );
        }

        return $public_tm_penalty;
    }

    /**
     * @param Languages $lang_handler
     * @param           $source_lang
     *
     * @return string
     * @throws InvalidArgumentException
     */
    private function validateSourceLang( Languages $lang_handler, $source_lang ): string {
        try {
            return $lang_handler->validateLanguage( $source_lang );
        } catch ( Exception $e ) {
            throw new InvalidArgumentException( "Missing source language." );
        }
    }

    /**
     * @param Languages $lang_handler
     * @param           $target_lang
     *
     * @return string
     * @throws InvalidArgumentException
     */
    private function validateTargetLangs( Languages $lang_handler, $target_lang ): string {
        $targets = explode( ',', $target_lang );
        $targets = array_map( 'trim', $targets );
        $targets = array_unique( $targets );

        if ( empty( $targets ) ) {
            throw new InvalidArgumentException( "Missing target language." );
        }

        try {
            $normalizedTargets = array_map(
                    function ( $lang ) use ( $lang_handler ) {
                        return $lang_handler->validateLanguage( $lang );
                    },
                    $targets
            );
        } catch ( Exception $e ) {
            throw new InvalidArgumentException( $e->getMessage() );
        }

        return implode( ',', $normalizedTargets );
    }

    /**
     * @param bool $project_completion
     * @param int  $mt_engine
     *
     * @return array
     * @throws Exception
     */
    private function appendFeaturesToProject( bool $project_completion, int $mt_engine ): array {
        $projectFeatures = [];

        if ( $project_completion ) {
            $feature                                   = new BasicFeatureStruct();
            $feature->feature_code                     = ProjectCompletion::FEATURE_CODE;
            $projectFeatures[ $feature->feature_code ] = $feature;
        }

        return $this->featureSet->filter(
                'filterCreateProjectFeatures', $projectFeatures, $this, $mt_engine
        );
    }

    /**
     * @param      $target_langs
     * @param      $mt_engine
     *
     * @return array
     * @see filterCreateProjectFeatures callback
     * @see NewController::appendFeaturesToProject()
     * @deprecated
     */
    private function generateTargetEngineAssociation( $target_langs, $mt_engine ): ?array { // TODO YYY remove map association, MMT now supports all languages. Remove from ProjectManager also
        $assoc = [];

        foreach ( explode( ",", $target_langs ) as $_matecatTarget ) {
            $assoc[ $_matecatTarget ] = $mt_engine;
        }

        return $assoc;
    }

    /**
     * @param $segmentation_rule
     *
     * @return string|null
     * @throws Exception
     */
    private function validateSegmentationRules( $segmentation_rule ): ?string {
        return Constants::validateSegmentationRules( $segmentation_rule );
    }

    /**
     * @param string $private_tm_key
     * @param string $private_tm_key_json
     *
     * @return array
     * @throws Exception
     */
    protected function validateTmAndKeys( string $private_tm_key = "", string $private_tm_key_json = "" ): array {

        $new_keys          = [];
        $private_tm_user   = null;
        $private_tm_pass   = null;
        $tm_prioritization = null;

        try {
            if ( !empty( $private_tm_key_json ) ) {

                // first check if `private_tm_key_json` is a valid JSON
                if ( !Utils::isJson( $private_tm_key_json ) ) {
                    throw new Exception( "private_tm_key_json is not a valid JSON" );
                }

                $schema = file_get_contents( AppConfig::$ROOT . '/inc/validation/schema/private_tm_key_json.json' );

                $validatorObject       = new JSONValidatorObject();
                $validatorObject->json = $private_tm_key_json;

                $validator = new JSONValidator( $schema, true );
                /** @var JSONValidatorObject $jsonObject */
                $jsonObject = $validator->validate( $validatorObject );

                $tm_prioritization = $jsonObject->decoded->tm_prioritization;

                $private_tm_key = array_map(
                        function ( $item ) {
                            return [
                                    'key'     => $item->key,
                                    'r'       => $item->read,
                                    'w'       => $item->write,
                                    'penalty' => $item->penalty ?? 0,
                            ];
                        },
                        $jsonObject->decoded->keys
                );

            } else {

                /**
                 * ----------------------------------
                 * Note 2021-05-28
                 * ----------------------------------
                 *
                 * We trim every space private_tm_key
                 *  to avoid misspelling errors
                 *
                 */
                $private_tm_key = preg_replace( "/\s+/", "", $private_tm_key );
                $private_tm_key = array_map(
                        [ $this, 'parseTmKeyInput' ],
                        explode( ",", $private_tm_key )
                );

            }
        } catch ( Exception $e ) {
            throw new InvalidArgumentException( $e->getMessage(), -6 );
        }

        if ( count( $private_tm_key ) > self::MAX_NUM_KEYS ) {
            throw new Exception( "Too much keys provided. Max number of keys is " . self::MAX_NUM_KEYS, -2 );
        }

        $private_tm_key = array_values( array_filter( $private_tm_key ) );

        //If a TMX file has been uploaded and no key was provided, create a new key.
        if ( empty( $private_tm_key ) ) {
            $uniformedFileObject = Upload::getUniformGlobalFilesStructure( $_FILES );
            foreach ( $uniformedFileObject as $_fileinfo ) {
                $pathinfo = AbstractFilesStorage::pathinfo_fix( $_fileinfo->name );
                if ( $pathinfo[ 'extension' ] == 'tmx' ) {
                    $private_tm_key[] = [ 'key' => 'new' ];
                    break;
                }
            }
        }

        //remove all empty entries
        foreach ( $private_tm_key as $__key_idx => $tm_key ) {
            //from api a key is sent and the value is 'new'
            if ( $tm_key[ 'key' ] == 'new' ) {

                try {
                    $APIKeySrv = new TMSService();
                    $newUser   = $APIKeySrv->createMyMemoryKey();

                    $private_tm_user = $newUser->id;
                    $private_tm_pass = $newUser->pass;

                    $private_tm_key[ $__key_idx ] =
                            [
                                    'key'     => $newUser->key,
                                    'name'    => 'New resource created for project {{pid}}',
                                    'penalty' => $tm_key[ 'penalty' ] ?? 0,
                                    'r'       => $tm_key[ 'r' ],
                                    'w'       => $tm_key[ 'w' ]
                            ];
                    $new_keys[]                   = $newUser->key;

                } catch ( Exception $e ) {
                    throw new Exception( $e->getMessage(), -1 );
                }

            } //if a string is sent, transform it into a valid array
            elseif ( !empty( $tm_key ) ) {

                $uid = $this->user->uid;

                $this_tm_key = [
                        'key'     => $tm_key[ 'key' ],
                        'name'    => null,
                        'penalty' => $tm_key[ 'penalty' ] ?? 0,
                        'r'       => $tm_key[ 'r' ],
                        'w'       => $tm_key[ 'w' ]
                ];

                /**
                 * Get the key description/name from the user keyring
                 */
                if ( $uid ) {
                    $mkDao = new MemoryKeyDao();

                    $keyRing = $mkDao->read(
                            ( new MemoryKeyStruct( [
                                    'uid'    => $uid,
                                    'tm_key' => new TmKeyStruct( $this_tm_key )
                            ] )
                            )
                    );

                    if ( count( $keyRing ) > 0 ) {
                        $this_tm_key[ 'name' ] = $keyRing[ 0 ]->tm_key->name;
                    }
                }

                $private_tm_key[ $__key_idx ] = $this_tm_key;
            }

            $private_tm_key[ $__key_idx ] = $this->sanitizeTmKeyArr( $private_tm_key[ $__key_idx ] );
        }

        return [
                $private_tm_user,
                $private_tm_pass,
                $private_tm_key,
                $new_keys,
                $tm_prioritization,
        ];
    }

    /**
     * @param $elem
     *
     * @return array
     */
    private static function sanitizeTmKeyArr( $elem ): array {

        $element                  = new TmKeyStruct( $elem );
        $element->complete_format = true;
        $elem                     = TmKeyManager::sanitize( $element );

        return $elem->toArray();
    }

    /**
     * @param null $id_team
     *
     * @return TeamStruct|null
     *
     * @throws Exception
     */
    private function validateTeam( $id_team = null ): ?TeamStruct {
        if ( !empty( $id_team ) ) {
            $dao = new MembershipDao();
            $org = $dao->findTeamByIdAndUser( $id_team, $this->user );

            if ( !$org ) {
                throw new Exception( 'Team and user membership does not match', -1 );
            }

            return $org;
        }

        return $this->user->getPersonalTeam();
    }

    /**
     * @param null $id_qa_model_template
     *
     * @return QAModelTemplateStruct|null
     * @throws Exception
     */
    private function validateQaModelTemplate( $id_qa_model_template = null ): ?QAModelTemplateStruct {
        if ( !empty( $id_qa_model_template ) ) {
            $qaModelTemplate = QAModelTemplateDao::get( [
                    'id'  => $id_qa_model_template,
                    'uid' => $this->getUser()->uid
            ] );

            // check if qa_model template exists
            if ( null === $qaModelTemplate ) {
                throw new InvalidArgumentException( 'This QA Model template does not exists or does not belongs to the logged in user' );
            }

            return $qaModelTemplate;
        }

        return null;
    }

    /**
     * @param $payable_rate_template_name
     * @param $payable_rate_template_id
     *
     * @return CustomPayableRateStruct|null
     * @throws Exception
     */
    private function validatePayableRateTemplate( $payable_rate_template_name = null, $payable_rate_template_id = null ): ?CustomPayableRateStruct {
        $payableRateModelTemplate = null;

        if ( !empty( $payable_rate_template_name ) ) {
            if ( empty( $payable_rate_template_id ) ) {
                throw new InvalidArgumentException( '`payable_rate_template_id` param is missing' );
            }
        }

        if ( !empty( $payable_rate_template_id ) ) {
            if ( empty( $payable_rate_template_name ) ) {
                throw new InvalidArgumentException( '`payable_rate_template_name` param is missing' );
            }
        }

        if ( !empty( $payable_rate_template_name ) and !empty( $payable_rate_template_id ) ) {

            $userId                   = $this->getUser()->uid;
            $payableRateModelTemplate = CustomPayableRateDao::getByIdAndUser( $payable_rate_template_id, $userId );

            if ( null === $payableRateModelTemplate ) {
                throw new InvalidArgumentException( 'Payable rate model id not valid' );
            }

            if ( $payableRateModelTemplate->name !== $payable_rate_template_name ) {
                throw new InvalidArgumentException( 'Payable rate model name not matching' );
            }
        }

        return $payableRateModelTemplate;
    }

    /**
     * Checks if id_qa_model is valid
     *
     * @param null $id_qa_model
     *
     * @return ModelStruct|null
     * @throws Exception
     */
    private function validateQaModel( $id_qa_model = null ): ?ModelStruct {
        if ( !empty( $id_qa_model ) ) {

            $qaModel = ModelDao::findById( $id_qa_model );

            // check if qa_model exists
            if ( null === $qaModel ) {
                throw new InvalidArgumentException( 'This QA Model does not exists' );
            }

            // check featureSet
            $qaModelLabel    = strtolower( $qaModel->label );
            $featureSetCodes = $this->getFeatureSet()->getCodes();

            if ( $qaModelLabel !== 'default' and !in_array( $qaModelLabel, $featureSetCodes ) ) {
                throw new InvalidArgumentException( 'This QA Model does not belong to the authenticated user' );
            }

            return $qaModel;
        }

        return null;
    }

    /**
     * @param null $mt_engine
     *
     * @return string|null
     * @throws Exception
     */
    private function validateUserMTEngine( $mt_engine = null ): ?string {
        // any other engine than Match
        if ( $mt_engine !== null and $mt_engine > 1 ) {
            try {
                EnginesFactory::getInstanceByIdAndUser( $mt_engine, $this->user->uid );
            } catch ( Exception $exception ) {
                throw new InvalidArgumentException( $exception->getMessage() );
            }
        }

        return $mt_engine;
    }

    /**
     * @param null $mmt_glossaries
     *
     * @return string|null
     */
    private function validateMMTGlossaries( $mmt_glossaries = null ): ?string {
        if ( !empty( $mmt_glossaries ) ) {
            try {
                $mmtGlossaries = html_entity_decode( $mmt_glossaries );

                ( new MMTValidator )->validate(
                        ValidatorObject::fromArray( [
                                'glossaryString' => $mmtGlossaries,
                        ] )
                );

                return $mmtGlossaries;
            } catch ( Exception $exception ) {
                throw new InvalidArgumentException( $exception->getMessage() );
            }
        }

        return null;
    }

    /**
     * Validate DeepL params
     *
     * @param null $deepl_formality
     *
     * @return string|null
     */
    private function validateDeepLFormality( $deepl_formality = null ): ?string {

        if ( !empty( $deepl_formality ) ) {

            $allowedFormalities = [
                    'default',
                    'prefer_less',
                    'prefer_more'
            ];

            if ( !in_array( $deepl_formality, $allowedFormalities ) ) {
                throw new InvalidArgumentException( "Incorrect DeepL formality value (default, prefer_less and prefer_more are the allowed values)" );
            }

            return $deepl_formality;
        }

        return null;
    }

    /**
     * Validate `dialect_strict` param vs target languages
     *
     * Example: {"it-IT": true, "en-US": false, "fr-FR": false}
     *
     * @param      $target_lang
     * @param null $dialect_strict
     *
     * @return string|null
     */
    private function validateDialectStrictParam( $target_lang, $dialect_strict = null ): ?string {
        if ( !empty( $dialect_strict ) ) {

            $dialect_strict   = trim( html_entity_decode( $dialect_strict ) );
            $target_languages = preg_replace( '/\s+/', '', $target_lang );
            $targets          = explode( ',', trim( $target_languages ) );

            // first check if `dialect_strict` is a valid JSON
            if ( !Utils::isJson( $dialect_strict ) ) {
                throw new InvalidArgumentException( "dialect_strict is not a valid JSON" );
            }

            $dialectStrictObj = json_decode( $dialect_strict, true );

            foreach ( $dialectStrictObj as $lang => $value ) {
                if ( !in_array( $lang, $targets ) ) {
                    throw new InvalidArgumentException( 'Wrong `dialect_strict` object, language, ' . $lang . ' is not one of the project target languages' );
                }

                if ( !is_bool( $value ) ) {
                    throw new InvalidArgumentException( 'Wrong `dialect_strict` object, not boolean declared value for ' . $lang );
                }
            }

            return html_entity_decode( $dialect_strict );
        }

        return null;
    }

    /**
     * @param null $filters_extraction_parameters
     * @param null $filters_extraction_parameters_template_id
     *
     * @return FiltersConfigTemplateStruct|null
     * @throws Exception
     */
    private function validateFiltersExtractionParameters( $filters_extraction_parameters = null, $filters_extraction_parameters_template_id = null ): ?FiltersConfigTemplateStruct {
        if ( !empty( $filters_extraction_parameters ) ) {

            // first check if `filters_extraction_parameters` is a valid JSON
            if ( !Utils::isJson( $filters_extraction_parameters ) ) {
                throw new InvalidArgumentException( "filters_extraction_parameters is not a valid JSON" );
            }

            $schema = file_get_contents( AppConfig::$ROOT . '/inc/validation/schema/filters_extraction_parameters.json' );

            $validatorObject       = new JSONValidatorObject();
            $validatorObject->json = $filters_extraction_parameters;

            $validator = new JSONValidator( $schema );
            $validator->validate( $validatorObject );

            $config = new FiltersConfigTemplateStruct();
            $config->hydrateAllDto( json_decode( $filters_extraction_parameters, true ) );

            return $config;

        }

        if ( !empty( $filters_extraction_parameters_template_id ) ) {

            $filtersTemplate = FiltersConfigTemplateDao::getByIdAndUser( $filters_extraction_parameters_template_id, $this->getUser()->uid );

            if ( $filtersTemplate === null ) {
                throw new InvalidArgumentException( "filters_extraction_parameters_template_id not valid" );
            }

            return $filtersTemplate;
        }

        return null;
    }

    /**
     * YYY json validation
     *
     * @param int|null    $mt_qe_workflow_template_id
     * @param string|null $mt_qe_workflow_template_raw_parameters
     *
     * @return MTQEWorkflowParams
     * @throws Exception
     */
    private function validateMTQEParametersOrDefault( ?int $mt_qe_workflow_template_id = null, ?string $mt_qe_workflow_template_raw_parameters = null ): MTQEWorkflowParams {

        if ( !empty( $mt_qe_workflow_template_raw_parameters ) ) {

            // first check if `mt_qe_workflow_template_raw_parameters` is a valid JSON
            if ( !Utils::isJson( $mt_qe_workflow_template_raw_parameters ) ) {
                throw new InvalidArgumentException( "mt_qe_workflow_template_raw_parameters is not a valid JSON" );
            }

            $schema = file_get_contents( AppConfig::$ROOT . '/inc/validation/schema/mt_qe_workflow_params.json' );

            $validatorObject       = new JSONValidatorObject();
            $validatorObject->json = $mt_qe_workflow_template_raw_parameters;

            $validator  = new JSONValidator( $schema, true );
            $jsonObject = $validator->validate( $validatorObject );

            /** @var JSONValidatorObject $jsonObject */
            return new MTQEWorkflowParams( (array)( $jsonObject->decoded ) );

        } elseif ( !empty( $mt_qe_workflow_template_id ) ) {

            $mtQeWorkflowTemplate = MTQEWorkflowTemplateDao::getByIdAndUser( $mt_qe_workflow_template_id, $this->getUser()->uid );

            if ( $mtQeWorkflowTemplate === null ) {
                throw new InvalidArgumentException( "mt_qe_workflow_template_id not valid" );
            }

            return $mtQeWorkflowTemplate->params;

        } else {
            return new MTQEWorkflowParams();
        }

    }

    /**
     * @param int|null $mt_qe_workflow_payable_rate_template_id
     *
     * @return MTQEPayableRateBreakdowns
     * @throws Exception
     */
    private function validateMTQEPayableRateBreakdownsOrDefault( ?int $mt_qe_workflow_payable_rate_template_id = null ): MTQEPayableRateBreakdowns {

        if ( !empty( $mt_qe_workflow_payable_rate_template_id ) ) {

            $mtQeWorkflowTemplate = MTQEPayableRateTemplateDao::getByIdAndUser( $mt_qe_workflow_payable_rate_template_id, $this->getUser()->uid );

            if ( $mtQeWorkflowTemplate === null ) {
                throw new InvalidArgumentException( "mt_qe_workflow_payable_rate_template_id not valid" );
            }

            return $mtQeWorkflowTemplate->breakdowns;

        }

        return new MTQEPayableRateBreakdowns;

    }

    /**
     * @param null $xliff_parameters
     * @param null $xliff_parameters_template_id
     *
     * @return array|mixed|null
     * @throws Exception
     */
    private function validateXliffParameters( $xliff_parameters = null, $xliff_parameters_template_id = null ) {
        if ( !empty( $xliff_parameters ) ) {

            // first check if `xliff_parameters` is a valid JSON
            if ( !Utils::isJson( $xliff_parameters ) ) {
                throw new InvalidArgumentException( "xliff_parameters is not a valid JSON" );
            }

            $schema = file_get_contents( AppConfig::$ROOT . '/inc/validation/schema/xliff_parameters_rules_content.json' );

            $validatorObject       = new JSONValidatorObject();
            $validatorObject->json = $xliff_parameters;

            $validator = new JSONValidator( $schema, true );
            $validator->validate( $validatorObject );

            return json_decode( $xliff_parameters, true ); // decode again because we need an associative array and not stdClass
        }

        if ( !empty( $xliff_parameters_template_id ) ) {

            $xliffConfigTemplate = XliffConfigTemplateDao::getByIdAndUser( $xliff_parameters_template_id, $this->getUser()->uid );

            if ( $xliffConfigTemplate === null ) {
                throw new InvalidArgumentException( "xliff_parameters_template_id not valid" );
            }

            return $xliffConfigTemplate->rules->getArrayCopy();
        }

        return null;
    }

    /**
     * @param $tmKeyString
     *
     * @return array|null
     * @throws Exception
     */
    private function parseTmKeyInput( $tmKeyString ): ?array {
        $tmKeyString = trim( $tmKeyString );
        $tmKeyInfo   = explode( ":", $tmKeyString );
        $read        = true;
        $write       = true;

        $permissionString = $tmKeyInfo[ 1 ] ?? null;

        //If the key is not set, return null. It will be filtered in the next lines.
        if ( empty( $tmKeyInfo[ 0 ] ) ) {
            return null;
        } //if permissions are set, check if they are allowed or not and eventually set permissions

        //permission string check
        switch ( $permissionString ) {
            case 'r':
                $write = false;
                break;
            case 'w':
                $read = false;
                break;
            case 'rw':
            case ''  :
            case null:
                break;
            //permission string value is not allowed
            default:
                $allowed_permissions = implode( ", ", TmKeyPermissions::$_accepted_grants );
                throw new Exception( "Invalid permission modifier string. Allowed: <empty>, $allowed_permissions" );
        }

        return [
                'key' => $tmKeyInfo[ 0 ],
                'r'   => $read,
                'w'   => $write,
        ];
    }

}