<?php

namespace Controller\API\App;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\Abstracts\Authentication\CookieManager;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\ScanDirectoryForConvertedFiles;
use Exception;
use InvalidArgumentException;
use Model\ConnectedServices\GDrive\Session;
use Model\DataAccess\Database;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FilesStorage\FilesStorageFactory;
use Model\Jobs\MetadataDao as JobsMetadataDao;
use Model\LQA\QAModelTemplate\QAModelTemplateDao;
use Model\LQA\QAModelTemplate\QAModelTemplateStruct;
use Model\PayableRates\CustomPayableRateDao;
use Model\PayableRates\CustomPayableRateStruct;
use Model\ProjectManager\ProjectManager;
use Model\Projects\MetadataDao;
use Model\Teams\MembershipDao;
use Model\Teams\TeamStruct;
use Model\Xliff\XliffConfigTemplateDao;
use Model\Xliff\XliffConfigTemplateStruct;
use Plugins\Features\ProjectCompletion;
use Utils\ActiveMQ\ClientHelpers\ProjectQueue;
use Utils\Constants\Constants;
use Utils\Constants\ProjectStatus;
use Utils\Engines\EnginesFactory;
use Utils\Engines\Lara;
use Utils\Engines\Validators\Contracts\EngineValidatorObject;
use Utils\Engines\Validators\DeepLEngineOptionsValidator;
use Utils\Engines\Validators\DeeplFormalityValidator;
use Utils\Engines\Validators\IntentoEngineOptionsValidator;
use Utils\Engines\Validators\IntentoRoutingValidator;
use Utils\Engines\Validators\MMTGlossaryValidator;
use Utils\Langs\Languages;
use Utils\Registry\AppConfig;
use Utils\TmKeyManagement\TmKeyManager;
use Utils\TmKeyManagement\TmKeyStruct;
use Utils\Tools\CatUtils;
use Utils\Tools\Utils;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

class CreateProjectController extends AbstractStatefulKleinController
{

    use ScanDirectoryForConvertedFiles;

    private array $data = [];
    private array $metadata = [];

    public function getData(): array
    {
        return $this->data;
    }

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws Exception
     */
    public function create(): void
    {
        $this->featureSet->loadFromUserEmail($this->user->email);
        $this->data = $this->validateTheRequest();

        // SET SOURCE COOKIE
        CookieManager::setCookie(
            Constants::COOKIE_SOURCE_LANG,
            $this->data['source_lang'],
            [
                'expires' => time() + (86400 * 365),
                'path' => '/',
                'domain' => AppConfig::$COOKIE_DOMAIN,
                'secure' => true,
                'httponly' => true,
                'samesite' => 'None',
            ]
        );

        // SET TARGET COOKIE
        CookieManager::setCookie(
            Constants::COOKIE_TARGET_LANG,
            $this->data['target_lang'],
            [
                'expires' => time() + (86400 * 365),
                'path' => '/',
                'domain' => AppConfig::$COOKIE_DOMAIN,
                'secure' => true,
                'httponly' => true,
                'samesite' => 'None',
            ]
        );

        //Search in fileNames if there's a zip file. If it's present, get filenames and add them instead of the zip file.
        $fs = FilesStorageFactory::create();
        $uploadDir = AppConfig::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $_COOKIE['upload_token'];
        $filesFound = $this->getFilesList($fs, $this->data['file_names_list'], $uploadDir);

        $projectManager = new ProjectManager();
        $projectStructure = $projectManager->getProjectStructure();

        $projectStructure['project_name'] = $this->data['project_name'];
        $projectStructure['private_tm_key'] = $this->data['private_tm_key'];
        $projectStructure['uploadToken'] = $_COOKIE['upload_token'];
        $projectStructure['array_files'] = $filesFound['arrayFiles']; //list of file names
        $projectStructure['array_files_meta'] = $filesFound['arrayFilesMeta']; //list of file metadata
        $projectStructure['source_language'] = $this->data['source_lang'];
        $projectStructure['target_language'] = explode(',', $this->data['target_lang']);
        $projectStructure['job_subject'] = $this->data['job_subject'];
        $projectStructure['mt_engine'] = $this->data['mt_engine'];
        $projectStructure['tms_engine'] = $this->data['tms_engine'] ?? 1;
        $projectStructure['status'] = ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS;
        $projectStructure['public_tm_penalty'] = $this->data['public_tm_penalty'];
        $projectStructure['pretranslate_100'] = $this->data['pretranslate_100'];
        $projectStructure['pretranslate_101'] = $this->data['pretranslate_101'];
        $projectStructure['dialect_strict'] = $this->data['dialect_strict'];
        $projectStructure['only_private'] = $this->data['only_private'];
        $projectStructure['due_date'] = $this->data['due_date'];
        $projectStructure['target_language_mt_engine_association'] = $this->data['target_language_mt_engine_association'];
        $projectStructure['user_ip'] = Utils::getRealIpAddr();
        $projectStructure['HTTP_HOST'] = AppConfig::$HTTPHOST;
        $projectStructure['tm_prioritization'] = (!empty($this->data['tm_prioritization'])) ? $this->data['tm_prioritization'] : null;
        $projectStructure['character_counter_mode'] = (!empty($this->data['character_counter_mode'])) ? $this->data['character_counter_mode'] : null;
        $projectStructure['character_counter_count_tags'] = (!empty($this->data['character_counter_count_tags'])) ? $this->data['character_counter_count_tags'] : null;
        $projectStructure[JobsMetadataDao::SUBFILTERING_HANDLERS] = $this->data[JobsMetadataDao::SUBFILTERING_HANDLERS];

        // GDrive session instance
        if (isset($_SESSION["gdrive_session"])) {
            $projectStructure['session'] = $_SESSION["gdrive_session"];
            $projectStructure['session']['uid'] = $this->user->uid;
        }

        // MT Extra params
        $engine = EnginesFactory::getInstance($this->data['mt_engine']);

        foreach ($engine->getConfigurationParameters() as $param) {
            if ($this->data[$param] !== null) {
                $projectStructure[$param] = $this->data[$param];
            }
        }

        if (!empty($this->data['filters_extraction_parameters'])) {
            $projectStructure['filters_extraction_parameters'] = $this->data['filters_extraction_parameters'];
        }

        if (!empty($this->data['xliff_parameters'])) {
            $projectStructure['xliff_parameters'] = $this->data['xliff_parameters'];
        }

        // with the qa template id
        if (!empty($this->data['qa_model_template'])) {
            $projectStructure['qa_model_template'] = $this->data['qa_model_template']->getDecodedModel();
        }

        if (!empty($this->data['payable_rate_model_template'])) {
            $projectStructure['payable_rate_model'] = $this->data['payable_rate_model_template'];
            $projectStructure['payable_rate_model_id'] = $this->data['payable_rate_model_template']->id;
        }

        $projectStructure['metadata'] = $this->metadata;
        $projectStructure['userIsLogged'] = true;
        $projectStructure['uid'] = $this->user->uid;
        $projectStructure['id_customer'] = $this->user->email;
        $projectStructure['owner'] = $this->user->email;
        $projectManager->setTeam($this->data['team']); // set the team object to avoid a useless query

        //set features override
        $projectStructure['project_features'] = $this->data['project_features'];

        //reserve a project id from the sequence
        $projectStructure['id_project'] = Database::obtain()->nextSequence(Database::SEQ_ID_PROJECT)[0];
        $projectStructure['ppassword'] = Utils::randomString(12);

        $projectManager->sanitizeProjectStructure();
        $fs::moveFileFromUploadSessionToQueuePath($_COOKIE['upload_token']);

        ProjectQueue::sendProject($projectStructure);

        $this->clearSessionFiles();
        $this->assignLastCreatedPid($projectStructure['id_project']);

        $this->response->json([
            'data' => [
                'id_project' => (int)$projectStructure['id_project'],
                'password' => $projectStructure['ppassword']
            ],
            'errors' => [],
        ]);
    }

    /**
     * Validates and processes the incoming request parameters to build a structured data array.
     *
     * This method retrieves and sanitizes input parameters from the request object,
     * ensuring data integrity and security. It organizes the extracted parameters
     * into a comprehensive array of values required for further processing. It also performs
     * additional transformations, such as decoding JSON, merging and filtering arrays, as well as
     * applying default values where necessary.
     *
     * @return array An associative array containing sanitized and processed request data.
     * @throws Exception
     */
    private function validateTheRequest(): array
    {
        $file_name = filter_var($this->request->param('file_name'), FILTER_UNSAFE_RAW, ['flags' => FILTER_FLAG_STRIP_LOW]);
        $source_lang = filter_var($this->request->param('source_lang'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);
        $target_lang = filter_var($this->request->param('target_lang'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);
        $job_subject = filter_var($this->request->param('job_subject'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);
        $due_date = filter_var($this->request->param('due_date'), FILTER_SANITIZE_NUMBER_INT);
        $mt_engine = filter_var($this->request->param('mt_engine'), FILTER_SANITIZE_NUMBER_INT);
        $disable_tms_engine_flag = filter_var($this->request->param('disable_tms_engine'), FILTER_VALIDATE_BOOLEAN);
        $pretranslate_100 = filter_var($this->request->param('pretranslate_100'), FILTER_SANITIZE_NUMBER_INT);
        $pretranslate_101 = filter_var($this->request->param('pretranslate_101'), FILTER_SANITIZE_NUMBER_INT);
        $tm_prioritization = filter_var($this->request->param('tm_prioritization'), FILTER_SANITIZE_NUMBER_INT);
        $id_team = filter_var($this->request->param('id_team'), FILTER_SANITIZE_NUMBER_INT, ['flags' => FILTER_REQUIRE_SCALAR]);
        $project_completion = filter_var($this->request->param('project_completion'), FILTER_VALIDATE_BOOLEAN);
        $get_public_matches = filter_var($this->request->param('get_public_matches'), FILTER_VALIDATE_BOOLEAN);
        $public_tm_penalty = filter_var($this->request->param('public_tm_penalty'), FILTER_SANITIZE_NUMBER_INT);
        $character_counter_count_tags = filter_var($this->request->param('character_counter_count_tags'), FILTER_VALIDATE_BOOLEAN);
        $character_counter_mode = filter_var($this->request->param('character_counter_mode'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW]);
        $dialect_strict = filter_var($this->request->param('dialect_strict'), FILTER_SANITIZE_SPECIAL_CHARS);
        $filters_extraction_parameters = filter_var($this->request->param('filters_extraction_parameters'), FILTER_SANITIZE_SPECIAL_CHARS);
        $xliff_parameters = filter_var($this->request->param('xliff_parameters'), FILTER_SANITIZE_SPECIAL_CHARS);
        $xliff_parameters_template_id = filter_var($this->request->param('xliff_parameters_template_id'), FILTER_SANITIZE_NUMBER_INT);
        $qa_model_template_id = filter_var($this->request->param('qa_model_template_id'), FILTER_SANITIZE_NUMBER_INT);
        $qa_model_template = filter_var($this->request->param('qa_model_template'), FILTER_SANITIZE_SPECIAL_CHARS);
        $payable_rate_template_id = filter_var($this->request->param('payable_rate_template_id'), FILTER_SANITIZE_NUMBER_INT);

        $mt_quality_value_in_editor = filter_var(
            $this->request->param('mt_quality_value_in_editor'),
            FILTER_SANITIZE_NUMBER_INT,
            [
                'filter' => FILTER_VALIDATE_INT,
                'flags' => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => 85, 'min_range' => 76, 'max_range' => 102]
            ]
        ); // used to set the absolute value of an MT match (previously fixed to 85)

        $payable_rate_template = filter_var($this->request->param('payable_rate_template'), FILTER_SANITIZE_SPECIAL_CHARS);
        $private_keys_list = filter_var(
            $this->request->param('private_keys_list'),
            FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            ['flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW | FILTER_FLAG_NO_ENCODE_QUOTES]
        );

        // MT SETTINGS
        $enable_mt_analysis = filter_var($this->request->param('enable_mt_analysis'), FILTER_VALIDATE_BOOLEAN);

        // The UI ask for case-sensitive matching true/false.
        // Negate the validated boolean because the MMT default flag is ignore_glossary_case.
        // true becomes false, false (or invalid/missing) becomes true.
        $mmt_ignore_glossary_case = filter_var($this->request->param('mmt_ignore_glossary_case'), FILTER_VALIDATE_BOOLEAN);

        $mmt_glossaries = filter_var($this->request->param('mmt_glossaries'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);
        $mmt_activate_context_analyzer = filter_var($this->request->param('mmt_activate_context_analyzer'), FILTER_VALIDATE_BOOLEAN);
        $intento_provider = filter_var($this->request->param('intento_provider'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);
        $intento_routing = filter_var($this->request->param('intento_routing'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);
        $lara_glossaries = filter_var($this->request->param('lara_glossaries'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);
        $lara_style = filter_var($this->request->param('lara_style'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);
        $deepl_id_glossary = filter_var($this->request->param('deepl_id_glossary'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);
        $deepl_formality = filter_var($this->request->param('deepl_formality'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);
        $deepl_engine_type = filter_var($this->request->param('deepl_engine_type'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);

        $array_keys = json_decode($private_keys_list, true);
        $array_keys = array_values(array_merge($array_keys['ownergroup'], $array_keys['mine'], $array_keys['anonymous']));

        $arFiles = explode('@@SEP@@', html_entity_decode($file_name, ENT_QUOTES, 'UTF-8'));

        // Build project name from input or fallback:
        // - If empty or invalid, uses current datetime; if exactly 1 file, derives from that filename.
        // - Accepts an array of ['name' => <filePath>] items.
        $project_name = CatUtils::sanitizeOrFallbackProjectName(
            $this->request->param('project_name', ''),
            array_map(fn($v): array => ['name' => $v], $arFiles)
        );

        if (!empty($array_keys)) {
            //remove duplicates
            $_array_unique = array_map(function ($v) {
                return $v['key'];
            }, $array_keys);

            $_array_unique = array_unique($_array_unique);

            $array_keys = array_values(array_intersect_key($array_keys, $_array_unique));
        }

        $private_tm_key = array_filter($array_keys, self::sanitizeTmKeyArr(...));
        $mt_engine = ($mt_engine != null ? $mt_engine : 0);
        $only_private = (!is_null($get_public_matches) && !$get_public_matches);
        $due_date = (empty($due_date) ? null : Utils::mysqlTimestamp($due_date));

        $engineStruct = null;
        // any other engine than Match
        if ($mt_engine !== null and $mt_engine > 1) {
            try {
                $engineStruct = EnginesFactory::getInstanceByIdAndUser($mt_engine, $this->user->uid);
            } catch (Exception $exception) {
                throw new InvalidArgumentException($exception->getMessage());
            }
        }

        (new DeepLEngineOptionsValidator())->validate(
            EngineValidatorObject::fromArray(
                [
                    'engineStruct' => $engineStruct,
                    'deepl_engine_type' => $deepl_engine_type,
                    'deepl_formality' => $deepl_formality,
                    'deepl_id_glossary' => $deepl_id_glossary
                ]
            )
        );

        (new IntentoEngineOptionsValidator())->validate(
            EngineValidatorObject::fromArray(
                [
                    'engineStruct' => $engineStruct,
                    'intento_provider' => $intento_provider,
                    'intento_routing' => $intento_routing
                ]
            )
        );

        // validate Lara style
        if(!empty($lara_style)){
            $lara_style = Lara::validateLaraStyle($lara_style);
        }

        /**
         * Represents a generic data variable that can hold a variety of information.
         *
         * This variable can be used to store different types of data, such as strings,
         * integers, arrays, or objects, depending on the context where it is applied.
         * Its value and type are dynamic and determined during runtime based on usage.
         *
         * @var mixed $data The data container allowing for versatile usage scenarios.
         */
        $data = [
            'file_name' => $file_name,
            'project_name' => $project_name,
            'source_lang' => $source_lang,
            'target_lang' => $target_lang,
            'job_subject' => $job_subject,
            'pretranslate_100' => $pretranslate_100,
            'pretranslate_101' => $pretranslate_101,
            'tm_prioritization' => $tm_prioritization ?? null,
            'id_team' => $id_team,
            'enable_mt_analysis' => $enable_mt_analysis ?? null,
            'mmt_ignore_glossary_case' => $mmt_ignore_glossary_case,
            'mmt_glossaries' => (!empty($mmt_glossaries)) ? $mmt_glossaries : null,
            'mmt_activate_context_analyzer' => $mmt_activate_context_analyzer ?? null,
            'intento_provider' => (!empty($intento_provider)) ? $intento_provider : null,
            'intento_routing' => (!empty($intento_routing)) ? $intento_routing : null,
            'lara_glossaries' => (!empty($lara_glossaries)) ? $lara_glossaries : null,
            'lara_style' => (!empty($lara_style)) ? $lara_style : null,
            'deepl_id_glossary' => (!empty($deepl_id_glossary)) ? $deepl_id_glossary : null,
            'deepl_formality' => (!empty($deepl_formality)) ? $deepl_formality : null,
            'deepl_engine_type' => (!empty($deepl_engine_type)) ? $deepl_engine_type : null,
            'project_completion' => $project_completion,
            'get_public_matches' => $get_public_matches,
            'character_counter_count_tags' => (!empty($character_counter_count_tags)) ? $character_counter_count_tags : null,
            'character_counter_mode' => (!empty($character_counter_mode)) ? $character_counter_mode : null,
            'dialect_strict' => (!empty($dialect_strict)) ? $dialect_strict : null,
            'filters_extraction_parameters' => (!empty($filters_extraction_parameters)) ? $filters_extraction_parameters : null,
            'xliff_parameters' => (!empty($xliff_parameters)) ? $xliff_parameters : null,
            'xliff_parameters_template_id' => (!empty($xliff_parameters_template_id)) ? $xliff_parameters_template_id : null,
            'qa_model_template' => (!empty($qa_model_template)) ? $qa_model_template : null,
            'qa_model_template_id' => (!empty($qa_model_template_id)) ? $qa_model_template_id : null,
            'payable_rate_template' => (!empty($payable_rate_template)) ? $payable_rate_template : null,
            'payable_rate_template_id' => (!empty($payable_rate_template_id)) ? $payable_rate_template_id : null,
            'array_keys' => (!empty($array_keys)) ? $array_keys : [],
            'mt_engine' => $mt_engine,
            'disable_tms_engine_flag' => $disable_tms_engine_flag,
            'private_tm_key' => $private_tm_key,
            'only_private' => $only_private,
            'mt_quality_value_in_editor' => (!empty($mt_quality_value_in_editor)) ? $mt_quality_value_in_editor : 85,
            'due_date' => (empty($due_date) ? null : Utils::mysqlTimestamp($due_date)),
            'file_names_list' => $arFiles,
            'public_tm_penalty' => $public_tm_penalty,

            /**
             * Subfiltering configuration (as string input):
             *
             * 1. String "null" or "" (empty string): subfiltering is disabled
             * 2. '[]' (JSON string empty array) or parameter omitted: default subfiltering is applied.
             * 3. JSON-encoded options (e.g., "[\"markup\",\"twig\"]"): custom subfiltering is applied using the provided handlers.
             *
             * Note:
             * - The values above are expected as strings (e.g., "[]"), not native PHP types.
             */
            JobsMetadataDao::SUBFILTERING_HANDLERS => json_encode(
                $this->validateSubfilteringOptions($this->request->param(JobsMetadataDao::SUBFILTERING_HANDLERS, '[]'))
            ),

        ];

        if ($disable_tms_engine_flag) {
            $data['tms_engine'] = 0; //remove default MyMemory
        }

        if (empty($file_name)) {
            throw new InvalidArgumentException("Missing file name.", -1);
        }

        if (empty($job_subject)) {
            throw new InvalidArgumentException("Missing job subject.", -5);
        }

        if ($pretranslate_100 != 1 and $pretranslate_100 != 0) {
            throw new InvalidArgumentException("Invalid pretranslate_100 value", -6);
        }

        if ($pretranslate_101 !== null and $pretranslate_101 != 1 && $pretranslate_101 != 0) {
            throw new InvalidArgumentException("Invalid pretranslate_101 value", -6);
        }

        $data['public_tm_penalty'] = (!empty($public_tm_penalty)) ? $this->validatePublicTMPenalty(
            (int)$public_tm_penalty
        ) : null;
        $data['source_lang'] = $this->validateSourceLang(Languages::getInstance(), $data['source_lang']);
        $data['target_lang'] = $this->validateTargetLangs(Languages::getInstance(), $data['target_lang']);
        $data['mt_engine'] = $this->validateUserMTEngine($data['mt_engine']);
        $data['mmt_glossaries'] = $this->validateMMTGlossaries($data['mmt_glossaries']);
        $data['qa_model_template'] = $this->validateQaModelTemplate(
            $data['qa_model_template'],
            $data['qa_model_template_id']
        );
        $data['payable_rate_model_template'] = $this->validatePayableRateTemplate(
            $data['payable_rate_template'],
            $data['payable_rate_template_id']
        );
        $data['dialect_strict'] = $this->validateDialectStrictParam($data['target_lang'], $data['dialect_strict']);
        $data['filters_extraction_parameters'] = $this->validateFiltersExtractionParameters(
            $data['filters_extraction_parameters']
        );
        $data['xliff_parameters'] = $this->validateXliffParameters(
            $data['xliff_parameters'],
            $data['xliff_parameters_template_id']
        );
        $data['project_features'] = $this->appendFeaturesToProject($data['project_completion'], $data['mt_engine']);
        $data['target_language_mt_engine_association'] = $this->generateTargetEngineAssociation(
            $data['target_lang'],
            $data['mt_engine']
        );
        $data['team'] = $this->setTeam($id_team);

        $this->setMetadataFromPostInput($data);

        return $data;
    }

    /**
     * Validates the provided subfiltering options by attempting to decode them as JSON.
     *
     * This method ensures that the input string is a valid JSON-encoded structure.
     * If the decoding process encounters an error, it returns an empty array to enforce
     * the default subfiltering behavior. Otherwise, it returns the decoded JSON data.
     *
     * @param string $subfiltering_handlers A JSON-encoded string representing subfiltering options.
     *
     * @return ?array The decoded JSON data as an associative array, or an empty array if an error occurs.
     * @throws Exception
     */
    private function validateSubfilteringOptions(string $subfiltering_handlers): ?array
    {
        if ($subfiltering_handlers == 'none') {
            // subfiltering is disabled
            $subfiltering_handlers = 'null';
        }

        $validatorObject = new JSONValidatorObject($subfiltering_handlers);
        $validator = new JSONValidator('subfiltering_handlers.json', true);
        $validator->validate($validatorObject);

        if (is_null($validatorObject->getValue())) {
            return null;
        }

        return $validatorObject->getValue();
    }


    /**
     * @param $elem
     *
     * @return array
     */
    private static function sanitizeTmKeyArr($elem): array
    {
        $element = new TmKeyStruct($elem);
        $element->complete_format = true;
        $elem = TmKeyManager::sanitize($element);

        return $elem->toArray();
    }

    /**
     * This function sets metadata property from input params.
     *
     * @param array $data
     *
     * @throws Exception
     */
    private function setMetadataFromPostInput(array $data = []): void
    {
        // new raw counter model
        $options = [MetadataDao::WORD_COUNT_TYPE_KEY => MetadataDao::WORD_COUNT_RAW];

        if (isset($data['speech2text'])) {
            $options['speech2text'] = $data['speech2text'];
        }

        if (isset($data['segmentation_rule'])) {
            $options['segmentation_rule'] = $data['segmentation_rule'];
        }

        if (isset($data['mt_quality_value_in_editor'])) {
            $options[MetadataDao::MT_QUALITY_VALUE_IN_EDITOR] = $data['mt_quality_value_in_editor'];
        }

        $this->metadata = $options;
    }

    /**
     * @param int|null $public_tm_penalty
     *
     * @return int|null
     */
    private function validatePublicTMPenalty(?int $public_tm_penalty = null): ?int
    {
        if ($public_tm_penalty < 0 || $public_tm_penalty > 100) {
            throw new InvalidArgumentException("Invalid public_tm_penalty value (must be between 0 and 100)", -6);
        }

        return $public_tm_penalty;
    }

    /**
     * @param Languages $lang_handler
     * @param           $source_lang
     *
     * @return string
     */
    private function validateSourceLang(Languages $lang_handler, $source_lang): string
    {
        try {
            $lang_handler->validateLanguage($source_lang);
        } catch (Exception $e) {
            throw new InvalidArgumentException($e->getMessage(), -3);
        }

        return $source_lang;
    }

    /**
     * @param Languages $lang_handler
     * @param           $target_lang
     *
     * @return string
     */
    private function validateTargetLangs(Languages $lang_handler, $target_lang): string
    {
        $targets = explode(',', $target_lang);
        $targets = array_map('trim', $targets);
        $targets = array_unique($targets);

        if (empty($targets)) {
            throw new InvalidArgumentException("Missing target language.", -4);
        }

        try {
            foreach ($targets as $target) {
                $lang_handler->validateLanguage($target);
            }
        } catch (Exception $e) {
            throw new InvalidArgumentException($e->getMessage(), -4);
        }

        return implode(',', $targets);
    }

    /**
     * Check if MT engine (except MyMemory) belongs to user
     *
     * @param int $mt_engine
     *
     * @return int
     */
    private function validateUserMTEngine(int $mt_engine): int
    {
        if ($mt_engine > 1) {
            try {
                EnginesFactory::getInstanceByIdAndUser($mt_engine, $this->user->uid);
            } catch (Exception $exception) {
                throw new InvalidArgumentException($exception->getMessage(), -2);
            }
        }

        return $mt_engine;
    }

    /**
     * Validate `mmt_glossaries` string
     *
     * @param null $mmt_glossaries
     *
     * @return string|null
     */
    private function validateMMTGlossaries($mmt_glossaries = null): ?string
    {
        if (!empty($mmt_glossaries)) {
            try {
                $mmtGlossaries = html_entity_decode($mmt_glossaries);

                (new MMTGlossaryValidator)->validate(
                    EngineValidatorObject::fromArray([
                        'glossaryString' => $mmtGlossaries,
                    ])
                );

                return $mmtGlossaries;
            } catch (Exception $exception) {
                throw new InvalidArgumentException($exception->getMessage(), -6);
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
    private function validateDeepLFormalityParams($deepl_formality = null): ?string
    {
        if (!empty($deepl_formality)) {
            $allowedFormalities = [
                'default',
                'prefer_less',
                'prefer_more'
            ];

            if (!in_array($deepl_formality, $allowedFormalities)) {
                throw new InvalidArgumentException("Not allowed value of DeepL formality", -6);
            }

            return $deepl_formality;
        }

        return null;
    }

    private function validateDeepLEngineType(?string $deepl_engine_type = null): ?string
    {
        if (!empty($deepl_engine_type)) {
            $allowedEngineTypes = [
                'prefer_quality_optimized',
                'latency_optimized',
            ];

            if (!in_array($deepl_engine_type, $allowedEngineTypes)) {
                throw new InvalidArgumentException("Not allowed value of DeepL engine type", -7);
            }

            return $deepl_engine_type;
        }

        return null;
    }

    /**
     * @param null $qa_model_template
     * @param null $qa_model_template_id
     *
     * @return QAModelTemplateStruct|null
     * @throws Exception
     */
    private function validateQaModelTemplate(
        $qa_model_template = null,
        $qa_model_template_id = null
    ): ?QAModelTemplateStruct {
        if (!empty($qa_model_template)) {
            $json = html_entity_decode($qa_model_template);

            $model = json_decode($json, true);
            $json = [
                "model" => $model,
            ];
            $json = json_encode($json);

            $validatorObject = new JSONValidatorObject($json);
            $validator = new JSONValidator('qa_model.json', true);
            $validator->validate($validatorObject);

            $QAModelTemplateStruct = new QAModelTemplateStruct();
            $QAModelTemplateStruct->hydrateFromJSON($json);
            $QAModelTemplateStruct->uid = $this->user->uid;

            return $QAModelTemplateStruct;
        } elseif (!empty($qa_model_template_id) and $qa_model_template_id > 0) {
            $qaModelTemplate = QAModelTemplateDao::get([
                'id' => $qa_model_template_id,
                'uid' => $this->getUser()->uid
            ]);

            // check if qa_model template exists
            if (null === $qaModelTemplate) {
                throw new InvalidArgumentException(
                    'This QA Model template does not exists or does not belongs to the logged in user'
                );
            }

            return $qaModelTemplate;
        }

        return null;
    }

    /**
     * @param null $payable_rate_template
     * @param null $payable_rate_template_id
     *
     * @return CustomPayableRateStruct|null
     * @throws Exception
     */
    private function validatePayableRateTemplate(
        $payable_rate_template = null,
        $payable_rate_template_id = null
    ): ?CustomPayableRateStruct {
        $payableRateModelTemplate = null;
        $userId = $this->getUser()->uid;

        if (!empty($payable_rate_template)) {
            $json = html_entity_decode($payable_rate_template);
            $validatorObject = new JSONValidatorObject($json);
            $validator = new JSONValidator('payable_rate.json', true);
            $validator->validate($validatorObject);

            $payableRateModelTemplate = new CustomPayableRateStruct();
            $payableRateModelTemplate->hydrateFromJSON($json);
            $payableRateModelTemplate->uid = $userId;
        } elseif (!empty($payable_rate_template_id) and $payable_rate_template_id > 0) {
            $payableRateModelTemplate = CustomPayableRateDao::getByIdAndUser($payable_rate_template_id, $userId);

            if (null === $payableRateModelTemplate) {
                throw new InvalidArgumentException('Payable rate model id not valid');
            }
        }

        return $payableRateModelTemplate;
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
    private function validateDialectStrictParam($target_lang, $dialect_strict = null): ?string
    {
        if (!empty($dialect_strict)) {
            $dialect_strict = trim(html_entity_decode($dialect_strict));
            $target_languages = preg_replace('/\s+/', '', $target_lang);
            $targets = explode(',', trim($target_languages));
            $dialectStrictObj = json_decode($dialect_strict, true);

            foreach ($dialectStrictObj as $lang => $value) {
                if (!in_array($lang, $targets)) {
                    throw new InvalidArgumentException(
                        'Wrong `dialect_strict` object, language, ' . $lang . ' is not one of the project target languages'
                    );
                }

                if (!is_bool($value)) {
                    throw new InvalidArgumentException(
                        'Wrong `dialect_strict` object, not boolean declared value for ' . $lang
                    );
                }
            }

            $dialect_strict = html_entity_decode($dialect_strict);
        }

        return $dialect_strict;
    }

    /**
     * @param null $filters_extraction_parameters
     *
     * @return mixed|null
     * @throws Exception
     */
    private function validateFiltersExtractionParameters($filters_extraction_parameters = null): mixed
    {
        if (!empty($filters_extraction_parameters)) {
            $json = html_entity_decode($filters_extraction_parameters);
            $validatorObject = new JSONValidatorObject($json);
            $validator = new JSONValidator('filters_extraction_parameters.json', true);
            $validator->validate($validatorObject);

            $filters_extraction_parameters = $validatorObject->getValue();
        }

        return $filters_extraction_parameters;
    }

    /**
     * @param null $xliff_parameters
     * @param null $xliff_parameters_template_id
     *
     * @return array|null
     * @throws Exception
     */
    private function validateXliffParameters($xliff_parameters = null, $xliff_parameters_template_id = null): ?array
    {
        if (!empty($xliff_parameters)) {
            $json = html_entity_decode($xliff_parameters);

            $validatorObject = new JSONValidatorObject($json);
            $validator = new JSONValidator('xliff_parameters_rules_wrapper.json', true);
            $validator->validate($validatorObject);

            $xliffConfigTemplate = new XliffConfigTemplateStruct();
            $xliffConfigTemplate->hydrateFromJSON($json);
            $xliff_parameters = $xliffConfigTemplate->rules->getArrayCopy();
        } elseif (!empty($xliff_parameters_template_id)) {
            $xliffConfigTemplate = XliffConfigTemplateDao::getByIdAndUser(
                $xliff_parameters_template_id,
                $this->getUser()->uid
            );

            if ($xliffConfigTemplate === null) {
                throw new Exception("xliff_parameters_template_id not valid");
            }

            $xliff_parameters = $xliffConfigTemplate->rules->getArrayCopy();
        }

        return $xliff_parameters;
    }

    /**
     * @param bool $project_completion
     * @param int $mt_engine
     *
     * @return array
     * @throws Exception
     */
    private function appendFeaturesToProject(bool $project_completion, int $mt_engine): array
    {
        $projectFeatures = [];

        if ($project_completion) {
            $feature = new BasicFeatureStruct();
            $feature->feature_code = ProjectCompletion::FEATURE_CODE;
            $projectFeatures[$feature->feature_code] = $feature;
        }

        return $this->featureSet->filter(
            'filterCreateProjectFeatures',
            $projectFeatures,
            $this,
            $mt_engine
        );
    }

    /**
     * @param      $target_langs
     * @param      $mt_engine
     *
     * @return array|null
     * @see filterCreateProjectFeatures callback
     * @see NewController::appendFeaturesToProject()
     * @deprecated
     */
    private function generateTargetEngineAssociation($target_langs, $mt_engine): ?array
    { // TODO YYY remove map association, MMT now supports all languages. Remove from ProjectManager also
        $assoc = [];

        foreach (explode(",", $target_langs) as $_matecatTarget) {
            $assoc[$_matecatTarget] = $mt_engine;
        }

        return $assoc;
    }

    /**
     * @param null $id_team
     *
     * @return TeamStruct|null
     * @throws Exception
     */
    private function setTeam($id_team = null): ?TeamStruct
    {
        if (is_null($id_team)) {
            return $this->user->getPersonalTeam();
        }

        // check for the team to be allowed
        $dao = new MembershipDao();
        $team = $dao->findTeamByIdAndUser($id_team, $this->user);

        if (!$team) {
            throw new Exception('Team and user memberships do not match');
        }

        return $team;
    }

    private function clearSessionFiles(): void
    {
        $gdriveSession = new Session();
        $gdriveSession->clearFileListFromSession();
    }

    private function assignLastCreatedPid($pid): void
    {
        $_SESSION['redeem_project'] = false;
        $_SESSION['last_created_pid'] = $pid;
    }
}