<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 17/11/25
 * Time: 17:28
 *
 */

namespace Utils\Engines\Validators;

use Exception;
use InvalidArgumentException;
use Utils\Engines\DeepL;
use Utils\Engines\Validators\Contracts\EngineValidatorObject;
use Utils\Validator\Contracts\AbstractValidator;
use Utils\Validator\Contracts\ValidatorObject;

class DeepLEngineOptionsValidator extends AbstractValidator
{

    /**
     * Validate DeepL params
     *
     * @param EngineValidatorObject $object
     * @return ValidatorObject|null
     */
    public function validate(ValidatorObject $object): ?ValidatorObject
    {
        if (empty($object->engineStruct) || !$object->engineStruct instanceof DeepL) {
            return null;
        }

        if (!empty($object->deepl_engine_type)) {
            $allowedEngineTypes = [
                'prefer_quality_optimized',
                'latency_optimized',
            ];

            if (!in_array($object->deepl_engine_type, $allowedEngineTypes)) {
                throw new InvalidArgumentException("Not allowed value of DeepL engine type", -7);
            }
        }

        if (!empty($object->deepl_formality)) {
            $allowedFormalities = [
                'default',
                'prefer_less',
                'prefer_more'
            ];

            if (!in_array($object->deepl_formality, $allowedFormalities)) {
                throw new InvalidArgumentException(
                    "Incorrect DeepL formality value (default, prefer_less and prefer_more are the allowed values)"
                );
            }
        }

        if (!empty($object->deepl_id_glossary)) {
            try {
                $apiKey = $object->engineStruct->getEngineRecord()->extra_parameters[ 'DeepL-Auth-Key' ];
                $object->engineStruct->setApiKey($apiKey);
                $object->engineStruct->getGlossary($object->deepl_id_glossary);
            } catch (Exception $e) {
                throw new InvalidArgumentException("DeepL glossary not found", -7, $e);
            }
        }

        return $object;
    }
}