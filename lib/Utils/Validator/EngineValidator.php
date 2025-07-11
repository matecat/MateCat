<?php

namespace Utils\Validator;

use Exception;
use Utils\Engines\AbstractEngine;
use Utils\Engines\EnginesFactory;

class EngineValidator {
    /**
     * @param int         $engineId
     * @param int         $uid
     * @param string|null $engineClass
     *
     * @return AbstractEngine
     * @throws Exception
     */
    public static function engineBelongsToUser( int $engineId, int $uid, ?string $engineClass = null ): AbstractEngine {
        $engine       = EnginesFactory::getInstance( $engineId );
        $engineRecord = $engine->getEngineRecord();

        if ( $engineRecord->uid != $uid ) {
            throw new Exception( "EnginesFactory doesn't belong to the user" );
        }

        if ( $engineRecord->active == 0 ) {
            throw new Exception( "EnginesFactory is no longer active" );
        }

        if ( $engineClass !== null and !is_a( $engine, $engineClass, true ) ) {
            throw new Exception( $engineId . "is not the expected $engineClass engine instance" );
        }

        return $engine;
    }
}