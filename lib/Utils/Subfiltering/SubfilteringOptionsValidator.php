<?php

namespace Utils\Subfiltering;

use Exception;
use Matecat\SubFiltering\Enum\InjectableFiltersTags;
use Matecat\SubFiltering\HandlersSorter;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

class SubfilteringOptionsValidator
{
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
    public static function validate(string $subfiltering_handlers): ?array
    {
        if ($subfiltering_handlers == 'none' || $subfiltering_handlers == 'null') {
            // subfiltering is disabled
            return null;
        }

        // check if the string is equals to the default subfiltering handlers
        $defaultHandlers = InjectableFiltersTags::tagNamesForArrayClasses(
            array_keys(HandlersSorter::getDefaultInjectedHandlers())
        );

        $subfiltering_handlers_array = json_decode($subfiltering_handlers, true);

        if(empty($subfiltering_handlers_array)){
            return [];
        }

        if(
            count($defaultHandlers ?? []) === count($subfiltering_handlers_array) &&
            empty(array_diff($defaultHandlers, $subfiltering_handlers_array))
        ){
            // subfiltering is default
            return [];
        }

        $validatorObject = new JSONValidatorObject($subfiltering_handlers);
        $validator = new JSONValidator('subfiltering_handlers.json', true);
        $validator->validate($validatorObject);

        if (is_null($validatorObject->getValue())) {
            return null;
        }

        return $validatorObject->getValue();
    }
}