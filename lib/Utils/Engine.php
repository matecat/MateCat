<?php
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
    public static function getInstance( $id ) {

        if ( !is_numeric( $id ) ) {
            throw new Exception( "Missing id engineRecord", -1 );
        }

        $engineDAO        = new EnginesModel_EngineDAO( Database::obtain() );
        $engineStruct     = EnginesModel_EngineStruct::getStruct();
        $engineStruct->id = $id;

        $eng = $engineDAO->setCacheTTL( 60 * 5 )->read( $engineStruct );

        /**
         * @var $engineRecord EnginesModel_EngineStruct
         */
        $engineRecord = @$eng[0];

        if ( empty( $engineRecord ) ) {
            throw new Exception( "Engine $id not found", -2 );
        }

        $className = 'Engines_' . $engineRecord->class_load;
        if( !class_exists( $className, true ) ){
            throw new Exception( "Engine Class $className not Found" );
        }

        return new $className( $engineRecord );

    }

    /**
     * @param EnginesModel_EngineStruct $engineRecord
     * @return mixed
     */
    public static function createTempInstance( $engineRecord ) {
        $className = 'Engines_' . $engineRecord->class_load;
        return new $className( $engineRecord );
    }

}