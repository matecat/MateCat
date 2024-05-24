<?php

namespace FiltersXliffConfig\Filters;

use FiltersXliffConfig\Filters\DTO\Json;
use FiltersXliffConfig\Filters\DTO\MSExcel;
use FiltersXliffConfig\Filters\DTO\MSPowerpoint;
use FiltersXliffConfig\Filters\DTO\MSWord;
use FiltersXliffConfig\Filters\DTO\Xml;
use FiltersXliffConfig\Filters\DTO\Yaml;
use JsonSerializable;

class FiltersConfigModel implements JsonSerializable
{
    private $yaml;
    private $xml;
    private $json;
    private $ms_word;
    private $ms_excel;
    private $ms_powerpoint;

    /**
     * FiltersConfigModel constructor.
     * @param Yaml $yaml
     * @param Xml $xml
     * @param Json $json
     * @param MSWord $ms_word
     * @param MSExcel $ms_excel
     * @param MSPowerpoint $ms_powerpoint
     */
    public function __construct(
        Yaml $yaml,
        Xml $xml,
        Json $json,
        MSWord $ms_word,
        MSExcel $ms_excel,
        MSPowerpoint $ms_powerpoint
    )
    {
        $this->yaml = $yaml;
        $this->xml = $xml;
        $this->json = $json;
        $this->ms_word = $ms_word;
        $this->ms_excel = $ms_excel;
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
}
