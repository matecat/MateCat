<?php

namespace Controller\API\App;

use Constants;
use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Conversion\FilesConverter;
use Exception;
use Filters\FiltersConfigTemplateDao;
use Filters\FiltersConfigTemplateStruct;
use INIT;
use InvalidArgumentException;
use Langs\InvalidLanguageException;
use Langs\Languages;
use ReflectionException;
use RuntimeException;
use Utils;

class ConvertFileController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @throws Exception
     */
    public function handle(): void {
        $data             = $this->validateTheRequest();
        $uploadTokenValue = $_COOKIE[ 'upload_token' ];
        $uploadDir        = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $uploadTokenValue;
        $errDir           = INIT::$STORAGE_DIR . DIRECTORY_SEPARATOR . 'conversion_errors' . DIRECTORY_SEPARATOR . $uploadTokenValue;

        if ( !Utils::isTokenValid( $uploadTokenValue ) ) {
            throw new RuntimeException( "Invalid Upload Token." );
        }

        $this->featureSet->loadFromUserEmail( $this->user->email );

        $converter = new FilesConverter(
                [ $data[ 'file_name' ] ],
                $data[ 'source_lang' ],
                $data[ 'target_lang' ],
                $uploadDir,
                $errDir,
                $uploadTokenValue,
                $data[ 'segmentation_rule' ],
                $this->featureSet,
                $data[ 'filters_extraction_parameters' ],
        );

        $converter->convertFiles();

        $result = $converter->getResult();

        $errorStatus = [];
        if ( $result->hasErrors() ) {
            $errorStatus = $result->getErrors();
        }

        $warningStatus = [];
        if ( $result->hasWarnings() ) {
            $warningStatus = $result->getWarnings();
        }

        // Upload errors handling
        if ( !empty( $errorStatus ) ) {
            $this->response->code( 400 );
        }

        $this->response->json( [ "errors" => $errorStatus, "warnings" => $warningStatus, "data" => $result->getData() ] );

    }

    /**
     * @return array
     * @throws Exception
     */
    private function validateTheRequest(): array {
        $file_name                                 = filter_var( $this->request->param( 'file_name' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $source_lang                               = filter_var( $this->request->param( 'source_lang' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $target_lang                               = filter_var( $this->request->param( 'target_lang' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $segmentation_rule                         = filter_var( $this->request->param( 'segmentation_rule' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $filters_extraction_parameters_template_id = filter_var( $this->request->param( 'filters_extraction_parameters_template_id' ), FILTER_SANITIZE_NUMBER_INT );
        $restarted_conversion                      = filter_var( $this->request->param( 'restarted_conversion' ), FILTER_VALIDATE_BOOLEAN );

        if ( empty( $file_name ) ) {
            throw new InvalidArgumentException( "Missing file name." );
        }

        if ( empty( $source_lang ) ) {
            throw new InvalidArgumentException( "Missing source language." );
        }

        if ( empty( $target_lang ) ) {
            throw new InvalidArgumentException( "Missing target language." );
        }

        if ( empty( $segmentation_rule ) ) {
            throw new InvalidArgumentException( "Missing segmentation rule." );
        }

        if ( !Utils::isValidFileName( $file_name ) ) {
            throw new InvalidArgumentException( "Invalid file name." );
        }

        $segmentation_rule             = Constants::validateSegmentationRules( $segmentation_rule );
        $filters_extraction_parameters = $this->validateFiltersExtractionParametersTemplateId( $filters_extraction_parameters_template_id );
        $source_lang                   = $this->validateSourceLang( $source_lang );
        $target_lang                   = $this->validateTargetLangs( $target_lang );

        return [
                'file_name'                                 => $file_name,
                'source_lang'                               => $source_lang,
                'target_lang'                               => $target_lang,
                'segmentation_rule'                         => $segmentation_rule,
                'filters_extraction_parameters'             => $filters_extraction_parameters,
                'filters_extraction_parameters_template_id' => (int)$filters_extraction_parameters_template_id,
                'restarted_conversion'                      => $restarted_conversion,
        ];
    }

    /**
     * @param $source_lang
     *
     * @return string
     * @throws InvalidLanguageException
     */
    private function validateSourceLang( $source_lang ): string {
        $lang_handler = Languages::getInstance();

        return $lang_handler->validateLanguage( $source_lang );
    }

    /**
     * @param $target_lang
     *
     * @return string
     * @throws InvalidLanguageException
     */
    private function validateTargetLangs( $target_lang ): string {
        $lang_handler = Languages::getInstance();

        return $lang_handler->validateLanguageListAsString( $target_lang );
    }

    /**
     * @param null $filters_extraction_parameters_template_id
     *
     * @return FiltersConfigTemplateStruct|null
     * @throws ReflectionException
     * @throws Exception
     */
    private function validateFiltersExtractionParametersTemplateId( $filters_extraction_parameters_template_id = null ): ?FiltersConfigTemplateStruct {

        if ( empty( $filters_extraction_parameters_template_id ) ) {
            return null;
        }

        $filtersTemplate = FiltersConfigTemplateDao::getByIdAndUser( $filters_extraction_parameters_template_id, $this->getUser()->uid );

        if ( $filtersTemplate === null ) {
            throw new Exception( "filters_extraction_parameters_template_id not valid" );
        }

        return $filtersTemplate;

    }

}