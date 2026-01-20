<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Model\Conversion\FilesConverter;
use Model\Filters\FiltersConfigTemplateDao;
use Model\Filters\FiltersConfigTemplateStruct;
use ReflectionException;
use RuntimeException;
use Utils\Constants\Constants;
use Utils\Langs\InvalidLanguageException;
use Utils\Langs\Languages;
use Utils\Registry\AppConfig;
use Utils\Tools\Utils;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;
use Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

class ConvertFileController extends KleinController
{

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        $data = $this->validateTheRequest();
        $uploadTokenValue = $_COOKIE['upload_token'];
        $uploadDir = AppConfig::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $uploadTokenValue;
        $errDir = AppConfig::$STORAGE_DIR . DIRECTORY_SEPARATOR . 'conversion_errors' . DIRECTORY_SEPARATOR . $uploadTokenValue;

        if (!Utils::isTokenValid($uploadTokenValue)) {
            throw new RuntimeException("Invalid Upload Token.");
        }

        $this->featureSet->loadFromUserEmail($this->user->email);

        $converter = new FilesConverter(
            [$data['file_name']],
            $data['source_lang'],
            $data['target_lang'],
            $uploadDir,
            $errDir,
            $uploadTokenValue,
            $data['icu_enabled'],
            $data['segmentation_rule'],
            $this->featureSet,
            $data['filters_extraction_parameters'],
        );

        $converter->convertFiles();

        $result = $converter->getResult();

        $errorStatus = [];
        if ($result->hasErrors()) {
            $errorStatus = $result->getErrors();
        }

        $warningStatus = [];
        if ($result->hasWarnings()) {
            $warningStatus = $result->getWarnings();
        }

        // Upload errors handling
        if (!empty($errorStatus)) {
            $this->response->code(400);
        }

        $this->response->json(["errors" => $errorStatus, "warnings" => $warningStatus, "data" => $result->getData()]);
    }

    /**
     * @return array
     * @throws Exception
     */
    private function validateTheRequest(): array
    {
        $file_name = filter_var($this->request->param('file_name'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);
        $source_lang = filter_var($this->request->param('source_lang'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $target_lang = filter_var($this->request->param('target_lang'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $segmentation_rule = filter_var($this->request->param('segmentation_rule'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $icu_enabled = filter_var($this->request->param('icu_enabled'), FILTER_VALIDATE_BOOLEAN);

        $filters_extraction_parameters_template = filter_var(
            $this->request->param('filters_extraction_parameters_template'),
            FILTER_SANITIZE_SPECIAL_CHARS,
            ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]
        );
        $filters_extraction_parameters_template_id = filter_var($this->request->param('filters_extraction_parameters_template_id'), FILTER_VALIDATE_INT, [
                'options' => [
                    'default' => 0,
                    'min_range' => 0,
                ],
                [
                    'flags' => FILTER_REQUIRE_SCALAR
                ]
            ]
        );
        $restarted_conversion = filter_var($this->request->param('restarted_conversion'), FILTER_VALIDATE_BOOLEAN);

        if (empty($file_name)) {
            throw new InvalidArgumentException("Missing file name.");
        }

        if (empty($source_lang)) {
            throw new InvalidArgumentException("Missing source language.");
        }

        if (empty($target_lang)) {
            throw new InvalidArgumentException("Missing target language.");
        }

        if (empty($segmentation_rule)) {
            throw new InvalidArgumentException("Missing segmentation rule.");
        }

        if (!Utils::isValidFileName($file_name)) {
            throw new InvalidArgumentException("Invalid file name.");
        }

        $segmentation_rule = Constants::validateSegmentationRules($segmentation_rule);
        $filters_extraction_parameters = $this->validateFiltersExtractionParametersTemplateId($filters_extraction_parameters_template, $filters_extraction_parameters_template_id);
        $source_lang = $this->validateSourceLang($source_lang);
        $target_lang = $this->validateTargetLangs($target_lang);

        return [
            'file_name' => $file_name,
            'source_lang' => $source_lang,
            'target_lang' => $target_lang,
            'segmentation_rule' => $segmentation_rule,
            'filters_extraction_parameters' => $filters_extraction_parameters,
            'filters_extraction_parameters_template_id' => (int)$filters_extraction_parameters_template_id,
            'restarted_conversion' => $restarted_conversion,
            'icu_enabled' => $icu_enabled,

        ];
    }

    /**
     * @param string $source_lang
     *
     * @return string
     * @throws InvalidLanguageException
     */
    private function validateSourceLang(string $source_lang): string
    {
        $lang_handler = Languages::getInstance();

        return $lang_handler->validateLanguage($source_lang);
    }

    /**
     * @param string $target_lang
     *
     * @return string
     * @throws InvalidLanguageException
     */
    private function validateTargetLangs(string $target_lang): string
    {
        $lang_handler = Languages::getInstance();

        return $lang_handler->validateLanguageListAsString($target_lang);
    }

    /**
     * @param string|null $filters_extraction_parameters_template
     * @param int|null $filters_extraction_parameters_template_id
     *
     * @return FiltersConfigTemplateStruct|null
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws ReflectionException
     * @throws Exception
     */
    private function validateFiltersExtractionParametersTemplateId(
        ?string $filters_extraction_parameters_template = null,
        ?int $filters_extraction_parameters_template_id = null
    ): ?FiltersConfigTemplateStruct {
        if (!empty($filters_extraction_parameters_template)) {
            $json = html_entity_decode($filters_extraction_parameters_template);
            $validatorObject = new JSONValidatorObject($json);
            $validator = new JSONValidator('filters_extraction_parameters.json', true);
            $validator->validate($validatorObject);

            $filtersTemplate = new FiltersConfigTemplateStruct();
            $filtersTemplate->hydrateFromJSON($json);
            $filtersTemplate->uid = $this->user->uid;

            return $filtersTemplate;
        } elseif (empty($filters_extraction_parameters_template_id)) {
            return null;
        }

        $filtersTemplate = FiltersConfigTemplateDao::getByIdAndUser($filters_extraction_parameters_template_id, $this->getUser()->uid);

        if ($filtersTemplate === null) {
            throw new Exception("filters_extraction_parameters_template_id not valid");
        }

        return $filtersTemplate;
    }

}