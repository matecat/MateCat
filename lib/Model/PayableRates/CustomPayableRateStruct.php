<?php

namespace PayableRates;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class CustomPayableRateStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct
{
    public $id;
    public $uid;
    public $version;
    public $name;
    public $breakdowns;

    /**
     * @return mixed
     */
    public function decodedBreakdowns()
    {
        return json_decode($this->breakdowns);
    }
}
