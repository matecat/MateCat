<?php

namespace Validator;

use Exception;
use Files\CSV;
use Langs\Languages;
use Utils;
use Validator\Contracts\AbstractValidator;
use Validator\Contracts\ValidatorObject;

class GlossaryCSVValidator extends AbstractValidator {

    /**
     * @inheritDoc
     */
    public function validate( ValidatorObject $object ) {

        if ( !$object instanceof GlossaryCSVValidatorObject ) {
            throw new Exception( 'Object given is not a valid instance of GlossaryCSVValidatorObject' );
        }

        $headers          = $this->getHeaders( $object->csv );
        $languagesHandler = Languages::getInstance();

        // 1. Validate languages
        if ( !$this->validateLanguages( $headers, $languagesHandler ) ) {
            return false;
        }

        $allowedLanguagesRegex = strtolower( implode( "|", array_keys( $languagesHandler->getEnabledLanguages() ) ) );

        // 2. Validate structure
        preg_match_all( '/^(forbidden)?(domain)?(subdomain)?(definition)?(((' . $allowedLanguagesRegex . ')((notes)?(example of use)?){2,})+$)/', implode( "", $headers ), $headerMatches );

        if ( empty( $headerMatches[ 0 ] ) ) {

            $this->errors[] = 'The order of the headers is incorrect, please change it to the one set out in <a href="https://guides.matecat.com/glossary-file-format" target="_blank">this support article</a>.';

            return false;
        }

        return true;
    }

    /**
     * @param $filePath
     *
     * @return array
     */
    private function getHeaders( $filePath ): array {
        $headers = CSV::headers( $filePath );
        $headers = array_map( 'Utils::trimAndLowerCase', $headers );

        return Utils::removeEmptyStringFromTail( $headers );
    }

    /**
     * @param $filePath
     *
     * @return int
     */
    public function getNumberOfLanguage( $filePath ) {

        $headers  = $this->getHeaders( $filePath );
        $skipKeys = [
                "forbidden",
                "domain",
                "subdomain",
                "definition",
                "notes",
                "example of use"
        ];

        $languages = array_diff( $headers, $skipKeys );

        if ( empty( $languages ) ) {
            return 0;
        }

        return count( $languages );
    }

    /**
     * @param array     $headers
     * @param Languages $languagesHandler
     *
     * @return bool
     */
    private function validateLanguages( array $headers, Languages $languagesHandler ): bool {

        $skipKeys = [
                "forbidden",
                "domain",
                "subdomain",
                "definition",
                "notes",
                "example of use"
        ];

        $languages = array_diff( $headers, $skipKeys );

        if ( count( $languages ) < 2 ) {
            $this->errors[] = 'Only one language detected, please upload a glossary with at least two languages. In case of doubts, refer to <a href="https://guides.matecat.com/glossary-file-format" target="_blank">this page</a>.';

            return false;
        }

        foreach ( $languages as $language ) {

            if ( empty( $language ) ) {
                $error          = 'The file contains and empty column header, you can find the correct column headers <a href="https://guides.matecat.com/glossary-file-format" target="_blank">here</a>.';
                $this->errors[] = $error;

                return false;
            }

            if ( !$languagesHandler->isValidLanguage( $language ) ) {

                $error          = ( strpos( $language, '_' ) !== false ) ? 'The column header <b>' . $language . '</b> contains an underscore, please replace it with a dash for the file to be valid for import. Ex: it_IT -> it-iT' : '<b>' . $language . '</b> is not a valid column header, you can find the correct column headers <a href="https://guides.matecat.com/glossary-file-format" target="_blank">here</a>.';
                $this->errors[] = $error;

                return false;
            }
        }

        return true;

    }
}