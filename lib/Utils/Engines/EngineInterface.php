<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 27/02/15
 * Time: 11.55
 *
 */

interface Engines_EngineInterface {

    /**
     * @param $_config
     *
     * @return Engines_Results_AbstractResponse
     */
    public function get( $_config );

    /**
     * @param $_config
     *
     * @return mixed
     */
    public function set( $_config );

    /**
     * @param $_config
     *
     * @return mixed
     */
    public function update( $_config );

    /**
     * @param $_config
     *
     * @return bool
     */
    public function delete( $_config );

    /**
     * @return mixed
     */
    public function getConfigStruct();

    /**
     * @return Engines_EngineInterface
     */
    public function setAnalysis(): Engines_EngineInterface;

    /**
     * @return EnginesModel_EngineStruct
     */
    public function getEngineRecord(): EnginesModel_EngineStruct;

    /**
     * @return bool
     */
    public function isAdaptiveMT(): bool;

    public function isTMS(): bool;

    /**
     * @return void
     */
    public function importMemory( string $filePath, string $memoryKey, Users_UserStruct $user );

    /**
     * @param array      $projectRow
     * @param array|null $segments
     *
     * @return void
     */
    public function syncMemories( array $projectRow, ?array $segments = [] );

    /**
     * @param TmKeyManagement_MemoryKeyStruct $memoryKey
     * @throws Exception
     * @return ?array
     */
    public function memoryExists( TmKeyManagement_MemoryKeyStruct $memoryKey ): ?array;

    /**
     * Deletes a specific memory key.
     *
     * @param array $memoryKey
     * @return array
     */

    public function deleteMemory( array $memoryKey ): array;
    
}