<?php

namespace Utils\Engines;

use Exception;
use Model\Engines\Structs\EngineStruct;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\UserStruct;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;

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
     * @param array<string, mixed> $_config
     */
    public function get(array $_config): GetMemoryResponse;

    /**
     * @param mixed $_config
     *
     * @return mixed
     */
    public function set($_config);

    /**
     * @param mixed $_config
     *
     * @return mixed
     */
    public function update($_config);

    /**
     * @param mixed $_config
     */
    public function delete($_config): bool;

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
     * @param array<string, mixed> $projectRow
     * @param array<int, mixed>|null $segments
     *
     * @return void
     */
    public function syncMemories(array $projectRow, ?array $segments = []);

    /**
     * @param MemoryKeyStruct $memoryKey
     *
     * @return array<string, mixed>|null
     * @throws Exception
     */
    public function memoryExists(MemoryKeyStruct $memoryKey): ?array;

    /**
     * Deletes a specific memory key.
     *
     * @param array<string, mixed> $memoryKey
     *
     * @return array<string, mixed>
     * @throws Exception
     */
    public function deleteMemory(array $memoryKey): array;

    /**
     * Determines if the provided memory belongs to the caller.
     *
     * @param MemoryKeyStruct $memoryKey
     *
     * @return array<string, mixed>|null Returns the memory key if the caller owns the memory, null otherwise.
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