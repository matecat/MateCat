<?php

namespace Utils\Engines\Validators;

use Exception;
use Model\DataAccess\IDatabase;
use Utils\Engines\DeepL;
use Utils\Engines\EnginesFactory;
use Utils\Engines\Validators\Contracts\EngineValidatorObject;
use Utils\Logger\LoggerFactory;
use Utils\Validator\Contracts\AbstractValidator;
use Utils\Validator\Contracts\ValidatorObjectInterface;

class DeepLEngineValidator extends AbstractValidator
{
    public function __construct(private readonly IDatabase $database)
    {
    }

    /**
     * @param EngineValidatorObject $object
     * @return ValidatorObjectInterface|null
     * @throws Exception
     * @throws \TypeError
     */
    public function validate(ValidatorObjectInterface $object): ?ValidatorObjectInterface
    {
        $engineStruct = $object->engineStruct ?? throw new Exception('Engine struct required');

        /** @var DeepL $newTestCreatedMT */
        $newTestCreatedMT = EnginesFactory::createTempInstance($engineStruct, $this->database);
        try {
            $config = $newTestCreatedMT->getConfigStruct();
            $config['segment'] = "Hello World";
            $config['source'] = "en-US";
            $config['target'] = "fr-FR";
            $config['pid'] = -(mt_rand(1000, PHP_INT_MAX));

            $newTestCreatedMT->get($config);
        } catch (Exception $e) {
            LoggerFactory::getLogger('engines')->error($e->getMessage());
            throw new Exception("Invalid DeepL API key.");
        }
        return null;
    }
}