<?php

namespace Features\Dqf\Service;

use Features\Dqf\Service\Struct\IBaseStruct;

interface ISession {

    public function getSessionId();
    public function filterHeaders( IBaseStruct $struct );
}