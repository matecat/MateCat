<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 17/11/25
 * Time: 14:03
 *
 */

namespace Utils\Engines\Validators\Contracts;

use Model\Engines\Structs\EngineStruct;
use stdClass;
use Utils\Validator\Contracts\ValidatorObject;

/**
 * @property ?EngineStruct $engineStruct
 * @property ?string $deepl_engine_type
 * @property ?string $deepl_formality
 * @property ?string $deepl_id_glossary
 * @property ?string $glossaryString
 * @property ?string $intento_provider
 * @property ?string $intento_routing
 */
class EngineValidatorObject extends ValidatorObject
{

    /**
     * @param stdClass $object
     *
     * @return self
     */
    public static function fromObject(stdClass $object): self
    {
        $that = new self();
        foreach (get_object_vars($object) as $key => $value) {
            $that->store[$key] = $value;
        }

        return $that;
    }

    /**
     * @param array<string, mixed> $array
     *
     * @return self
     */
    public static function fromArray(array $array): self
    {
        $that = new self();
        foreach ($array as $key => $value) {
            $that->store[$key] = $value;
        }

        return $that;
    }

}
