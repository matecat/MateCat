<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 17/11/25
 * Time: 18:12
 *
 */

namespace Utils\Engines\Validators;

use InvalidArgumentException;
use ReflectionException;
use Utils\Engines\Intento;
use Utils\Engines\Validators\Contracts\EngineValidatorObject;
use Utils\Validator\Contracts\AbstractValidator;
use Utils\Validator\Contracts\ValidatorObject;

class IntentoEngineOptionsValidator extends AbstractValidator
{
    /**
     * @param EngineValidatorObject $object
     * @return ValidatorObject|null
     * @throws ReflectionException
     */
    public function validate(ValidatorObject $object): ?ValidatorObject
    {
        if (empty($object->engineStruct) || !$object->engineStruct instanceof Intento) {
            return null;
        }

        $hasProvider = !empty($object->intento_provider);
        $hasRouting = !empty($object->intento_routing);

        if ($hasProvider && !$hasRouting) {
            $availableProviders = $object->engineStruct->getProviderList();

            if (!array_key_exists($object->intento_provider, $availableProviders)) {
                throw new InvalidArgumentException("Intento provider not valid.");
            }
        } elseif (!$hasProvider && $hasRouting) {
            $availableRoutings = $object->engineStruct->getRoutingList();

            if (!array_key_exists($object->intento_routing, $availableRoutings)) {
                throw new InvalidArgumentException("Intento routing not valid.");
            }
        }

        return null;
    }
}