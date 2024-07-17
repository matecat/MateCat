<?php

namespace Filters;

use DataAccess_AbstractDaoSilentStruct;
use Date\DateTimeUtil;
use FiltersXliffConfig\Filters\DTO\Json;
use FiltersXliffConfig\Filters\DTO\MSExcel;
use FiltersXliffConfig\Filters\DTO\MSPowerpoint;
use FiltersXliffConfig\Filters\DTO\MSWord;
use FiltersXliffConfig\Filters\DTO\Xml;
use FiltersXliffConfig\Filters\DTO\Yaml;
use JsonSerializable;

class FiltersConfigStruct extends DataAccess_AbstractDaoSilentStruct implements JsonSerializable
{
    public $id;
    public $name;
    public $uid;
    public $created_at;
    public $modified_at;
    public $deleted_at;
    private $yaml = null;
    private $xml = null;
    private $json = null;
    private $ms_word = null;
    private $ms_excel = null;
    private $ms_powerpoint = null;

    /**
     * @param Yaml|null $yaml
     */
    public function setYaml(?Yaml $yaml): void
    {
        $this->yaml = $yaml;
    }

    /**
     * @param Xml|null $xml
     */
    public function setXml(?Xml $xml): void
    {
        $this->xml = $xml;
    }

    /**
     * @param Json|null $json
     */
    public function setJson(?Json $json): void
    {
        $this->json = $json;
    }

    /**
     * @param MSWord|null $ms_word
     */
    public function setMsWord(?MSWord $ms_word): void
    {
        $this->ms_word = $ms_word;
    }

    /**
     * @param MSExcel|null $ms_excel
     */
    public function setMsExcel(?MSExcel $ms_excel): void
    {
        $this->ms_excel = $ms_excel;
    }

    /**
     * @param MSPowerpoint|null $ms_powerpoint
     */
    public function setMsPowerpoint(?MSPowerpoint $ms_powerpoint): void
    {
        $this->ms_powerpoint = $ms_powerpoint;
    }

    public function hydrateFromJSON( $json, $uid = null )
    {}

    /**
     * @return array|mixed
     * @throws \Exception
     */
    public function jsonSerialize()
    {
        return [
            'id'            => (int)$this->id,
            'uid'           => (int)$this->uid,
            'name'          => $this->name,
            'xml'           => $this->xml,
            'yaml'          => $this->yaml,
            'json'          => $this->json,
            'ms_word'       => $this->ms_word,
            'ms_excel'      => $this->ms_excel,
            'ms_powerpoint' => $this->ms_powerpoint,
            'createdAt'     => DateTimeUtil::formatIsoDate( $this->created_at ),
            'modifiedAt'    => DateTimeUtil::formatIsoDate( $this->modified_at ),
            'deletedAt'     => DateTimeUtil::formatIsoDate( $this->deleted_at ),
        ];
    }

    /**
     * @return false|string
     * @throws \Exception
     */
    public function __toString()
    {
        return json_encode([
            'id'            => (int)$this->id,
            'uid'           => (int)$this->uid,
            'name'          => $this->name,
            'xml'           => $this->xml,
            'yaml'          => $this->yaml,
            'json'          => $this->json,
            'ms_word'       => $this->ms_word,
            'ms_excel'      => $this->ms_excel,
            'ms_powerpoint' => $this->ms_powerpoint,
            'createdAt'     => DateTimeUtil::formatIsoDate( $this->created_at ),
            'modifiedAt'    => DateTimeUtil::formatIsoDate( $this->modified_at ),
            'deletedAt'     => DateTimeUtil::formatIsoDate( $this->deleted_at ),
        ]);
    }
}
