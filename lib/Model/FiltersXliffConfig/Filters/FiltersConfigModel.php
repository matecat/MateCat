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
    private $yaml = null;
    private $xml = null;
    private $json = null;
    private $ms_word = null;
    private $ms_excel = null;
    private $ms_powerpoint = null;

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
        ?Yaml $yaml = null,
        ?Xml $xml = null,
        ?Json $json = null,
        ?MSWord $ms_word = null,
        ?MSExcel $ms_excel = null,
        ?MSPowerpoint $ms_powerpoint = null
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
