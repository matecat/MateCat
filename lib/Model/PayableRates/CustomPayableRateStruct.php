<?php

namespace PayableRates;

use Analysis_PayableRates;
use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;
use Date\DateTimeUtil;
use DomainException;
use Exception;
use JsonSerializable;
use Utils;

class CustomPayableRateStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, JsonSerializable
{
    const MAX_BREAKDOWN_SIZE = 65535;

    public $id;
    public $uid;
    public $version;
    public $name;
    public $breakdowns;
    public $created_at;
    public $modified_at;
    public $deleted_at;

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
        $languages = \Langs_Languages::getInstance();
        $breakdowns = $this->getBreakdownsArray();

        // $isoSource and $isoTarget is in 'isocode' format
        // $source and $target are in 'rfc3066code' format
        $isoSource = $languages->convertLanguageToIsoCode($source);
        $isoTarget = $languages->convertLanguageToIsoCode($target);

        if($isoSource === null){
            return $breakdowns['default'];
        }

        if($isoTarget === null){
            return $breakdowns['default'];
        }

        $this->validateLanguage($isoSource);
        $this->validateLanguage($isoTarget);
        $this->validateLanguage($source);
        $this->validateLanguage($target);

        $resolveBreakdowns = Analysis_PayableRates::resolveBreakdowns($breakdowns, $source, $target);

        return (!empty($resolveBreakdowns)) ? $resolveBreakdowns : $breakdowns['default'];
    }

    /**
     * @param string $json
     * @return $this
     *
     * @throws Exception
     */
    public function hydrateFromJSON($json)
    {
        $json = json_decode($json, true);

        if(
            !isset($json['payable_rate_template_name']) and
            !isset($json['breakdowns'])
        ){
            throw new Exception("Cannot instantiate a new CustomPayableRateStruct. Invalid JSON provided.", 403);
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
     * @throws Exception
     */
    private function validateBreakdowns($breakdowns)
    {
        $size = mb_strlen(json_encode($breakdowns, JSON_NUMERIC_CHECK), '8bit');

        if($size > self::MAX_BREAKDOWN_SIZE){
            throw new Exception('`breakdowns` string is too large. Max size: 64kb', 400);
        }

        if(!isset($breakdowns['default'])){
            throw new DomainException('`default` node is MANDATORY in the breakdowns array.', 403);
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
        $languages = \Langs_Languages::getInstance();

        if(!$languages->isValidLanguage($lang, $format)){
            throw new \DomainException($lang . ' is not a supported language', 403);
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    public function jsonSerialize()
    {
        return [
            'id' => (int)$this->id,
            'uid' => (int)$this->uid,
            'version' => (int)$this->version,
            'payable_rate_template_name' => $this->name,
            'breakdowns' => $this->getBreakdownsArray(),
            'createdAt' => DateTimeUtil::formatIsoDate($this->created_at),
            'modifiedAt' => DateTimeUtil::formatIsoDate($this->modified_at),
            'deletedAt' => DateTimeUtil::formatIsoDate($this->deleted_at),
        ];
    }
}
