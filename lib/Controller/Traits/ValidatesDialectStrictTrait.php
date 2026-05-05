<?php

namespace Controller\Traits;

use Exception;
use InvalidArgumentException;
use Matecat\Locales\Languages;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;
use Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

trait ValidatesDialectStrictTrait
{
    /**
     * Validate `dialect_strict` param
     *
     * Example: {"it-IT": true, "en-US": false, "fr-FR": false}
     *
     * Structural validation (JSON object with BCP 47 keys and boolean values)
     * is delegated to JSON schema. Language code validation against the
     * supported-languages registry is performed in PHP.
     *
     * @param Languages $lang_handler
     * @param mixed $dialect_strict Raw input (typically a string from the HTTP request)
     *
     * @return array|null Decoded associative array, or null if input is empty
     *
     * @throws InvalidArgumentException  If a language code is not supported
     * @throws JSONValidatorException If JSON structure is invalid
     * @throws JsonValidatorGenericException If JSON is malformed
     * @throws Exception
     */
    private function validateDialectStrictParam(Languages $lang_handler, mixed $dialect_strict = null): ?array
    {
        if (!empty($dialect_strict)) {
            $dialect_strict = trim(html_entity_decode($dialect_strict));

            // Structural validation via JSON schema
            $jsonValidatorObject = new JSONValidatorObject($dialect_strict);
            $jsonValidator       = new JSONValidator('dialect_strict.json', true);
            $jsonValidator->validate($jsonValidatorObject);

            // Domain validation — ensure language codes are supported
            $decoded = $jsonValidatorObject->getValue(true);

            foreach ($decoded as $lang => $value) {
                try {
                    $lang_handler->validateLanguage($lang);
                } catch (Exception) {
                    throw new InvalidArgumentException(
                        'Wrong `dialect_strict` object, language, ' . $lang . ' is not supported'
                    );
                }
            }

            return $decoded;
        }

        return null;
    }
}
