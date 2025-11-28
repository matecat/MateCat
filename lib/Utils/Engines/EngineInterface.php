<?php

namespace Utils\Engines;

use Exception;
use Model\Engines\Structs\EngineStruct;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\UserStruct;
use Utils\Engines\Results\TMSAbstractResponse;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 27/02/15
 * Time: 11.55
 *
 */
interface EngineInterface
{

    /**
     * @param array $_config
     *
     * @return TMSAbstractResponse
     */
    public function get(array $_config);

    /**
     * @param $_config
     *
     * @return mixed
     */
    public function set($_config);

    /**
     * @param $_config
     *
     * @return mixed
     */
    public function update($_config);

    /**
     * @param $_config
     *
     * @return bool
     */
    public function delete($_config);

    /**
     * @return mixed
     */
    public function getConfigStruct();

    /**
     * @return EngineInterface
     */
    public function setAnalysis(): EngineInterface;

    /**
     * @param int $mt_penalty
     *
     * @return EngineInterface
     */
    public function setMTPenalty(int $mt_penalty): EngineInterface;

    /**
     * @return EngineStruct
     */
    public function getEngineRecord(): EngineStruct;

    /**
     * @return bool
     */
    public function isAdaptiveMT(): bool;

    public function isTMS(): bool;

    /**
     * @return void
     */
    public function importMemory(string $filePath, string $memoryKey, UserStruct $user);

    /**
     * @param array $projectRow
     * @param array|null $segments
     *
     * @return void
     */
    public function syncMemories(array $projectRow, ?array $segments = []);

    /**
     * @param MemoryKeyStruct $memoryKey
     *
     * @return ?array
     * @throws Exception
     */
    public function memoryExists(MemoryKeyStruct $memoryKey): ?array;

    /**
     * Deletes a specific memory key.
     *
     * @param array $memoryKey
     *
     * @return array
     * @throws Exception
     */

    public function deleteMemory(array $memoryKey): array;

    /**
     * Determines if the provided memory belongs to the caller.
     *
     * @param MemoryKeyStruct $memoryKey
     *
     * @return array|null Returns the memory key if the caller owns the memory, false otherwise.
     * @throws Exception
     */
    public function getMemoryIfMine(MemoryKeyStruct $memoryKey): ?array;

    /**
     * @param string $source
     * @param string $target
     * @param string $sentence
     * @param string $translation
     * @param string $mt_qe_engine_id
     *
     * @return float|null
     */
    public function getQualityEstimation(string $source, string $target, string $sentence, string $translation, string $mt_qe_engine_id = 'default'): ?float;
}