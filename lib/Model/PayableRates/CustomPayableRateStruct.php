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
     * @return string
     */
    public function breakdownsToJson()
    {
        return json_encode($this->breakdowns);
    }

    /**
     * @return array
     */
    public function getBreakdownsArray()
    {
        return (is_string($this->breakdowns) ? json_decode($this->breakdowns, true) : $this->breakdowns);
    }

    /**
     * @param string $source
     * @param string  $target
     *
     * @return array
     */
    public function getPayableRates($source, $target)
    {
        $breakdowns = $this->getBreakdownsArray();

        if ( isset( $breakdowns[ $source ][ $target ] ) ) {
            return $breakdowns[ $source ][ $target ];
        }

        if ( isset( $breakdowns[ $target ][ $source ] ) ) {
            return $breakdowns[ $target ][ $source ];
        }

        return $breakdowns['default'];
    }

    /**
     * @param string $json
     * @return $this
     *
     * @throws \Exception
     */
    public function hydrateFromJSON($json)
    {
        $json = json_decode($json, true);

        if(
            !isset($json['version']) and
            !isset($json['payable_rate_template_name']) and
            !isset($json['breakdowns'])
        ){
            throw new \Exception("Cannot instantiate a new CustomPayableRateStruct. Invalid JSON provided.");
        }

        $this->version = $json['version'];
        $this->name = $json['payable_rate_template_name'];
        $this->breakdowns = $json['breakdowns'];

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
            'breakdowns' => $this->getBreakdownsArray(),
        ];
    }
}
