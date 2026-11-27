<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 14/11/25
 * Time: 15:58
 *
 */

namespace Utils\Engines\Validators;

use DomainException;
use Exception;
use InvalidArgumentException;
use Lara\LaraException;
use Model\DataAccess\IDatabase;
use Model\Engines\Structs\EngineStruct;
use ReflectionException;
use TypeError;
use Utils\Engines\EnginesFactory;
use Utils\Engines\Lara;
use Utils\Engines\Validators\Contracts\EngineValidatorObject;
use Utils\Validator\Contracts\AbstractValidator;
use Utils\Validator\Contracts\ValidatorObjectInterface;

class LaraEngineValidator extends AbstractValidator
{

    public function __construct(private readonly IDatabase $database)
    {
    }

    /**
     * @param EngineValidatorObject $object
     * @return ValidatorObjectInterface|null
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function validate(ValidatorObjectInterface $object): ?ValidatorObjectInterface
    {
        if (!$object instanceof EngineValidatorObject || !$object->engineStruct instanceof EngineStruct) {
            throw new InvalidArgumentException('Invalid Lara engine validator object');
        }

        /** @var Lara $newTestCreatedMT */
        $newTestCreatedMT = EnginesFactory::createTempInstance($object->engineStruct, $this->database);
        $config = $newTestCreatedMT->getConfigStruct();
        $config['segment'] = "Hello World";
        $config['source'] = "en-US";
        $config['target'] = "it-IT";

        try {
            $newTestCreatedMT->get($config);
        } catch (LaraException $e) {
            throw new DomainException($e->getMessage(), $e->getCode(), $e);
        }

        return null;
    }

}
