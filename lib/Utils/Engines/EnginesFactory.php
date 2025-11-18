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
class EnginesFactory {

    /**
     * @param $id
     *
     * @return AbstractEngine
     * @throws Exception
     */
    public static function getInstance( $id ): AbstractEngine {

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

        $className = self::getFullyQualifiedClassName( $engineRecord->class_load );

        return new $className( $engineRecord );

    }

    /**
     * @param EngineStruct $engineRecord
     *
     * @return EngineInterface
     * @throws Exception
     */
    public static function createTempInstance( EngineStruct $engineRecord ): EngineInterface {
        $className                = self::getFullyQualifiedClassName( $engineRecord->class_load );
        $engineRecord->class_load = $className;

        return new $engineRecord->class_load( $engineRecord );
    }

    /**
     * @throws Exception
     */
    public static function getFullyQualifiedClassName( string $_className ): string {
        $className = 'Utils\Engines\\' . $_className; // guess for backward compatibility
        if ( !class_exists( $className ) ) {
            if ( !class_exists( $_className ) ) {
                throw new Exception( "Engine Class $className not Found" );
            }
            $className = $_className; // use the class name as is
        }

        return $className;
    }

    /**
     * @template T of AbstractEngine
     *
     * @param int              $engineId
     * @param int              $uid
     * @param ?class-string<T> $engineClass
     *
     * @return T
     * @throws Exception
     */
    public static function getInstanceByIdAndUser( int $engineId, int $uid, ?string $engineClass = null ): AbstractEngine {

        $engine       = self::getInstance( $engineId );
        $engineRecord = $engine->getEngineRecord();

        if ( $engineRecord->uid != $uid ) {
            throw new AuthorizationError( "Engine doesn't belong to the user" );
        }

        if ( $engineRecord->active == 0 ) {
            throw new DomainException( "Engine is no longer active" );
        }

        if ( $engineClass !== null and !is_a( $engine, $engineClass, true ) ) {
            throw new Exception( $engineId . " is not the expected $engineClass engine instance" );
        }

        return $engine;
    }

}