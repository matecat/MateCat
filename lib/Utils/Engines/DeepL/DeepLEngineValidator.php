<?php

namespace Utils\Engines\DeepL;

use Exception;
use InvalidArgumentException;
use Model\Engines\Structs\EngineStruct;
use Utils\Engines\DeepL;
use Utils\Engines\EngineInterface;
use Utils\Engines\EnginesFactory;

class DeepLEngineValidator
{
    /**
     * @param EngineStruct $engineStruct
     * @return EngineInterface
     * @throws Exception
     */
    public static function validate(EngineStruct $engineStruct): void
    {
        /** @var DeepL $newTestCreatedMT */
        $newTestCreatedMT = EnginesFactory::createTempInstance($engineStruct);
        try {
            $newTestCreatedMT->glossaries();
        } catch (Exception $exception) {
            throw new Exception("Invalid DeepL API key.");
        }
    }

    /**
     * Validate DeepL params
     *
     * @param string|null $deepl_formality
     *
     * @return string|null
     */
    public static function validateFormality(?string $deepl_formality = null): ?string
    {
        if (!empty($deepl_formality)) {
            $allowedFormalities = [
                'default',
                'prefer_less',
                'prefer_more'
            ];

            if (!in_array($deepl_formality, $allowedFormalities)) {
                throw new InvalidArgumentException(
                    "Incorrect DeepL formality value (default, prefer_less and prefer_more are the allowed values)"
                );
            }

            return $deepl_formality;
        }

        return null;
    }

    public static function validateEngineType(?string $deepl_engine_type = null): ?string
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

}