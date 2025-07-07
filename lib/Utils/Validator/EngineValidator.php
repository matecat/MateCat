<?php

namespace Validator;

use Engine;
use Exception;
use Utils\Engines\AbstractEngine;

class EngineValidator {
    /**
     * @param int  $engineId
     * @param int  $uid
     * @param null $engineClass
     *
     * @return \Utils\Engines\AbstractEngine
     * @throws Exception
     */
    public static function engineBelongsToUser( int $engineId, int $uid, $engineClass = null ): AbstractEngine {
        $engine       = Engine::getInstance( $engineId );
        $engineRecord = $engine->getEngineRecord();

        if ( $engineRecord->uid != $uid ) {
            throw new Exception( "Engine doesn't belong to the user" );
        }

        if ( $engineRecord->active == 0 ) {
            throw new Exception( "Engine is no longer active" );
        }

        if ( $engineClass !== null and !$engine instanceof $engineClass ) {
            throw new Exception( $engineId . "is not the expected $engineClass engine instance" );
        }

        return $engine;
    }
}