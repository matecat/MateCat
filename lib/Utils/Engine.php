<?php

use Model\Engines\EngineDAO;
use Model\Engines\EngineStruct;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 27/02/15
 * Time: 11.34
 *
 */

class Engine {

    /**
     * @param $id
     *
     * @return Engines_AbstractEngine
     * @throws Exception
     */
    public static function getInstance( $id ): Engines_AbstractEngine {

        if ( !is_numeric( $id ) ) {
            throw new Exception( "Missing id engineRecord", -1 );
        }

        $engineDAO        = new EngineDAO( Database::obtain() );
        $engineStruct     = EngineStruct::getStruct();
        $engineStruct->id = $id;

        $eng = $engineDAO->setCacheTTL( 60 * 5 )->read( $engineStruct );

        /**
         * @var $engineRecord EngineStruct
         */
        $engineRecord = $eng[ 0 ] ?? null;

        if ( empty( $engineRecord ) ) {
            throw new Exception( "Engine $id not found", -2 );
        }

        $className = 'Engines_' . $engineRecord->class_load;
        if ( !class_exists( $className ) ) {
            $className = $engineRecord->class_load;
            if ( !class_exists( $className ) ) {
                throw new Exception( "Engine Class $className not Found" );
            }
        }

        return new $className( $engineRecord );

    }

    /**
     * @param EngineStruct $engineRecord
     *
     * @return Engines_EngineInterface
     * @throws Exception
     */
    public static function createTempInstance( EngineStruct $engineRecord ): Engines_EngineInterface {

        $className = 'Engines_' . $engineRecord->class_load;
        if ( !class_exists( $className ) ) {
            $className = $engineRecord->class_load; // fully qualified class name
            if ( !class_exists( $className ) ) {
                $className = 'Utils\Engines\\' . $engineRecord->class_load; // guess
                if ( !class_exists( $className ) ) {
                    throw new Exception( "Engine Class $engineRecord->class_load not Found" );
                }
                // we found the right class name, overwrite the fake record
                $engineRecord->class_load = $className;
            }
        }

        return new $className( $engineRecord );
    }

}