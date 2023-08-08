<?php

namespace PayableRates;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;
use Date\DateTimeUtil;
use DateTime;
use Utils;

class CustomPayableRateStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, \JsonSerializable
{
    public $id;
    public $uid;
    public $version;
    public $name;
    public $breakdowns;
    public $created_at;
    public $modified_at;

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
        if(!Utils::isValidLanguage($source, 'isocode')){
            throw new \DomainException($source . ' is not a supported language');
        }

        if(!Utils::isValidLanguage($target, 'isocode')){
            throw new \DomainException($target . ' is not a supported language');
        }

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
            !isset($json['payable_rate_template_name']) and
            !isset($json['breakdowns'])
        ){
            throw new \Exception("Cannot instantiate a new CustomPayableRateStruct. Invalid JSON provided.");
        }

        $this->validateBreakdowns($json['breakdowns']);

        if(isset($json['version'])){
            $this->version = $json['version'];
        }

        $this->name = $json['payable_rate_template_name'];
        $this->breakdowns = $json['breakdowns'];

        return $this;
    }

    /**
     * @param $breakdowns
     */
    private function validateBreakdowns($breakdowns)
    {
        if(!isset($breakdowns['default'])){
            throw new \DomainException('`default` node is MANDATORY in the breakdowns array.');
        }

        unset($breakdowns['default']);

        foreach ($breakdowns as $language => $breakdown){

            if(!Utils::isValidLanguage($language, 'isocode')){
                throw new \DomainException($language . ' is not a supported language');
            }

            foreach ($breakdown as $targetLanguage => $rates){
                if(!Utils::isValidLanguage($targetLanguage, 'isocode')){
                    throw new \DomainException($targetLanguage . ' is not a supported language');
                }
            }
        }
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function jsonSerialize()
    {
        return [
            'id' => (int)$this->id,
            'uid' => (int)$this->uid,
            'version' => (int)$this->version,
            'name' => $this->name,
            'breakdowns' => $this->getBreakdownsArray(),
            'createdAt' => DateTimeUtil::formatIsoDate($this->created_at),
            'modifiedAt' => DateTimeUtil::formatIsoDate($this->modified_at),
        ];
    }
}
