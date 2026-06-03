<?php

namespace Utils\Engines;

use Controller\API\Commons\Exceptions\AuthorizationError;
use DomainException;
use Exception;
use Model\DataAccess\Database;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 27/02/15
 * Time: 11.34
 *
 */
class EnginesFactory
{

    /**
     * @template T of AbstractEngine
     * @param int $id
     * @param ?class-string<T> $engineClass
     *
     * @return T
     * @throws Exception
     */
    public static function getInstance(int $id, ?string $engineClass = null): AbstractEngine
    {
        $engineDAO = new EngineDAO(Database::obtain());
        $engineStruct = EngineStruct::getStruct();
        $engineStruct->id = $id;

        $eng = $engineDAO->setCacheTTL(60 * 5)->read($engineStruct);

        /** @var EngineStruct|null $engineRecord */
        $engineRecord = $eng[0] ?? null;

        if (empty($engineRecord)) {
            throw new Exception("Engine $id not found", -2);
        }

        $className = self::getFullyQualifiedClassName($engineRecord->class_load ?? throw new Exception("Engine $id has no class_load"));

        /** @var T $engine */
        $engine = new $className($engineRecord);

        if ($engineClass !== null and !is_a($engine, $engineClass, true)) {
            throw new Exception("Engine Id " . $id . " is not the expected $engineClass engine instance");
        }

        return $engine;
    }

    /**
     * @param EngineStruct $engineRecord
     *
     * @return EngineInterface
     * @throws Exception
     */
    public static function createTempInstance(EngineStruct $engineRecord): EngineInterface
    {
        $className = self::getFullyQualifiedClassName($engineRecord->class_load ?? throw new Exception("Engine has no class_load"));
        $engineRecord->class_load = $className;

        /** @var EngineInterface $engine */
        $engine = new $className($engineRecord);

        return $engine;
    }

    /**
     * @throws Exception
     */
    public static function getFullyQualifiedClassName(string $_className): string
    {
        $className = 'Utils\Engines\\' . $_className; // guess for backward compatibility
        if (!class_exists($className)) {
            if (!class_exists($_className)) {
                throw new Exception("Engine Class $className not Found");
            }
            $className = $_className; // use the class name as is
        }

        return $className;
    }

    /**
     * @template T of AbstractEngine
     *
     * @param int $engineId
     * @param int $uid
     * @param ?class-string<T> $engineClass
     *
     * @return T
     * @throws Exception
     */
    public static function getInstanceByIdAndUser(int $engineId, int $uid, ?string $engineClass = null): AbstractEngine
    {
        $engine = self::getInstance($engineId, $engineClass);
        $engineRecord = $engine->getEngineRecord();

        if ($engineRecord->uid != $uid) {
            throw new AuthorizationError("Engine doesn't belong to the user");
        }

        if ($engineRecord->active == 0) {
            throw new DomainException("Engine is no longer active");
        }

        return $engine;
    }

}