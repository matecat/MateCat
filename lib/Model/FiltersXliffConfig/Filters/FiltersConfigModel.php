<?php

namespace FiltersXliffConfig\Filters;

use FiltersXliffConfig\Filters\DTO\Json;
use FiltersXliffConfig\Filters\DTO\MSExcel;
use FiltersXliffConfig\Filters\DTO\MSPowerpoint;
use FiltersXliffConfig\Filters\DTO\MSWord;
use FiltersXliffConfig\Filters\DTO\Xml;
use FiltersXliffConfig\Filters\DTO\Yaml;
use JsonSerializable;
use Serializable;

class FiltersConfigModel implements JsonSerializable
{
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

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'xml' => $this->xml,
            'yaml' => $this->yaml,
            'json' => $this->json,
            'ms_word' => $this->ms_word,
            'ms_excel' => $this->ms_excel,
            'ms_powerpoint' => $this->ms_powerpoint,
        ];
    }

    /**
     * @inheritDoc
     */
    public function __toString()
    {
        return json_encode([
            'xml' => $this->xml,
            'yaml' => $this->yaml,
            'json' => $this->json,
            'ms_word' => $this->ms_word,
            'ms_excel' => $this->ms_excel,
            'ms_powerpoint' => $this->ms_powerpoint,
        ]);
    }
}
