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
        $shortSource = explode('-', $source);
        $shortTarget = explode('-', $target);

        $this->validateLanguage($shortSource[0]);
        $this->validateLanguage($shortTarget[0]);
        $this->validateLanguage($source);
        $this->validateLanguage($target);
        $breakdowns = $this->getBreakdownsArray();

        if ( isset( $breakdowns[ $source ] ) ) {
            if ( isset( $breakdowns[ $source ][ $target ] ) ) {
                return $breakdowns[ $source ][ $target ];
            }

            if ( isset( $breakdowns[ $source ][ $shortTarget[0] ] ) ) {
                return $breakdowns[ $source ][ $shortTarget[0] ];
            }
        }

        if ( isset( $breakdowns[ $shortSource[0] ] ) ) {
            if ( isset( $breakdowns[ $shortSource[0] ][ $target ] ) ) {
                return $breakdowns[ $shortSource[0] ][ $target ];
            }

            if ( isset( $breakdowns[ $shortSource[0] ][ $shortTarget[0] ] ) ) {
                return $breakdowns[ $shortSource[0] ][ $shortTarget[0] ];
            }
        }

        if ( isset( $breakdowns[ $target ] ) ) {
            if ( isset( $breakdowns[ $target ][ $source ] ) ) {
                return $breakdowns[ $target ][ $source ];
            }

            if ( isset( $breakdowns[ $target ][ $shortSource[0] ] ) ) {
                return $breakdowns[ $target ][ $shortSource[0] ];
            }
        }

        if ( isset( $breakdowns[ $shortTarget[0] ] ) ) {
            if ( isset( $breakdowns[ $shortTarget[0] ][ $source ] ) ) {
                return $breakdowns[ $shortTarget[0] ][ $source ];
            }

            if ( isset( $breakdowns[ $shortTarget[0] ][ $shortSource[0] ] ) ) {
                return $breakdowns[ $shortTarget[0] ][ $shortSource[0] ];
            }
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
            $this->validateLanguage($language);

            foreach ($breakdown as $targetLanguage => $rates){
                $this->validateLanguage($targetLanguage);
            }
        }
    }

    /**
     * @param $lang
     */
    private function validateLanguage($lang)
    {
        // rfc3066code --->  es-ES
        // isocode     --->  es
        $format = (strlen($lang) > 3) ? 'rfc3066code' : 'isocode';

        if(!Utils::isValidLanguage($lang, $format)){
            throw new \DomainException($lang . ' is not a supported language');
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
