<?php

namespace PayableRates;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class CustomPayableRateStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, \JsonSerializable
{
    public $id;
    public $uid;
    public $version;
    public $name;
    public $breakdowns;

    /**
     * @return mixed
     */
    public function breakdownsToJson()
    {
        return json_encode($this->breakdowns);
    }

    /**
     * @param $json
     * @return $this
     * @throws \Exception
     */
    public function hydrateFromJSON($json)
    {
        $json = json_decode($json);

        if(
            !isset($json->version) and
            !isset($json->payable_rate_template_name) and
            !isset($json->breakdowns)
        ){
            throw new \Exception("Cannot instantiate a new CustomPayableRateStruct. Invalid JSON provided.");
        }

        $this->version = $json->version;
        $this->name = $json->payable_rate_template_name;
        $this->breakdowns = $json->breakdowns;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'uid' => $this->uid,
            'version' => (int)$this->version,
            'name' => $this->name,
            'breakdowns' => (is_string($this->breakdowns) ? json_decode($this->breakdowns) : $this->breakdowns),
        ];
    }
}
