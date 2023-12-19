<?php

namespace Validator;

use Engine;
use Exception;

class EngineValidator
{
    /**
     * @param $engineId
     * @param $uid
     * @param $engineClass
     * @return \Engines_AbstractEngine
     * @throws Exception
     */
    public static function engineBelongsToUser($engineId, $uid, $engineClass = null)
    {
        $engine = Engine::getInstance( $engineId );
        $engineRecord = $engine->getEngineRecord();

        if($engineRecord->uid !== $uid){
            throw new Exception("Engine doesn't belong to the user");
        }

        if($engineRecord->active == 0){
            throw new Exception("Engine is no longer active");
        }

        if($engineClass !== null and !$engine instanceof $engineClass ){
            throw new Exception($engineId . "is not the expected $engineClass engine instance");
        }

        return $engine;
    }
}