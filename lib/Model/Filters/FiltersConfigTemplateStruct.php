<?php

namespace Model\Filters;

use DomainException;
use Exception;
use JsonSerializable;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\Filters\DTO\Dita;
use Model\Filters\DTO\Json;
use Model\Filters\DTO\MSExcel;
use Model\Filters\DTO\MSPowerpoint;
use Model\Filters\DTO\MSWord;
use Model\Filters\DTO\Xml;
use Model\Filters\DTO\Yaml;
use Utils\Date\DateTimeUtil;

class FiltersConfigTemplateStruct extends AbstractDaoSilentStruct implements JsonSerializable
{

    public ?int          $id            = null;
    public ?string       $name          = null;
    public ?int          $uid           = null;
    public ?string       $created_at    = null;
    public ?string       $modified_at   = null;
    public ?string       $deleted_at    = null;
    public ?Yaml         $yaml          = null;
    public ?Xml          $xml           = null;
    public ?Json         $json          = null;
    public ?MSWord       $ms_word       = null;
    public ?MSExcel      $ms_excel      = null;
    public ?MSPowerpoint $ms_powerpoint = null;
    public ?Dita         $dita          = null;

    /**
     * Get YAML filter configuration DTO.
     *
     * @return Yaml|null YAML configuration or null if not set.
     */
    public function getYaml(): ?Yaml
    {
        return $this->yaml;
    }

    /**
     * Get XML filter configuration DTO.
     *
     * @return Xml|null XML configuration or null if not set.
     */
    public function getXml(): ?Xml
    {
        return $this->xml;
    }

    /**
     * Get JSON filter configuration DTO.
     *
     * @return Json|null JSON configuration or null if not set.
     */
    public function getJson(): ?Json
    {
        return $this->json;
    }

    /**
     * Get Microsoft Word filter configuration DTO.
     *
     * @return MSWord|null MS Word configuration or null if not set.
     */
    public function getMsWord(): ?MSWord
    {
        return $this->ms_word;
    }

    /**
     * Get Microsoft Excel filter configuration DTO.
     *
     * @return MSExcel|null MS Excel configuration or null if not set.
     */
    public function getMsExcel(): ?MSExcel
    {
        return $this->ms_excel;
    }

    /**
     * Get Microsoft PowerPoint filter configuration DTO.
     *
     * @return MSPowerpoint|null MS PowerPoint configuration or null if not set.
     */
    public function getMsPowerpoint(): ?MSPowerpoint
    {
        return $this->ms_powerpoint;
    }

    /**
     * Get DITA filter configuration DTO.
     *
     * @return Dita|null DITA configuration or null if not set.
     */
    public function getDita(): ?Dita
    {
        return $this->dita;
    }

    /**
     * Set YAML filter configuration DTO.
     *
     * @param Yaml|null $yaml YAML configuration.
     *
     * @return void
     */
    public function setYaml(?Yaml $yaml): void
    {
        $this->yaml = $yaml;
    }

    /**
     * Set XML filter configuration DTO.
     *
     * @param Xml|null $xml XML configuration.
     *
     * @return void
     */
    public function setXml(?Xml $xml): void
    {
        $this->xml = $xml;
    }

    /**
     * Set JSON filter configuration DTO.
     *
     * @param Json|null $json JSON configuration.
     *
     * @return void
     */
    public function setJson(?Json $json): void
    {
        $this->json = $json;
    }

    /**
     * Set Microsoft Word filter configuration DTO.
     *
     * @param MSWord|null $ms_word MS Word configuration.
     *
     * @return void
     */
    public function setMsWord(?MSWord $ms_word): void
    {
        $this->ms_word = $ms_word;
    }

    /**
     * Set Microsoft Excel filter configuration DTO.
     *
     * @param MSExcel|null $ms_excel MS Excel configuration.
     *
     * @return void
     */
    public function setMsExcel(?MSExcel $ms_excel): void
    {
        $this->ms_excel = $ms_excel;
    }

    /**
     * Set Microsoft PowerPoint filter configuration DTO.
     *
     * @param MSPowerpoint|null $ms_powerpoint MS PowerPoint configuration.
     *
     * @return void
     */
    public function setMsPowerpoint(?MSPowerpoint $ms_powerpoint): void
    {
        $this->ms_powerpoint = $ms_powerpoint;
    }

    /**
     * Set DITA filter configuration DTO.
     *
     * @param Dita|null $dita DITA configuration.
     *
     * @return void
     */
    public function setDita(?Dita $dita): void
    {
        $this->dita = $dita;
    }

    /**
     * Instantiate and hydrate a DTO from array data, then assign it to the proper property.
     *
     * @param class-string $dtoClass Fully qualified DTO class name.
     * @param array        $data     DTO payload.
     *
     * @return void
     */
    protected function hydrateDtoFromArray(string $dtoClass, array $data): void
    {
        $dto = new $dtoClass();
        $dto->fromArray($data);

        switch ($dtoClass) {
            case Json::class:
                $this->setJson($dto);
                break;
            case Xml::class:
                $this->setXml($dto);
                break;
            case Yaml::class:
                $this->setYaml($dto);
                break;
            case MSExcel::class:
                $this->setMsExcel($dto);
                break;
            case MSWord::class:
                $this->setMsWord($dto);
                break;
            case MSPowerpoint::class:
                $this->setMsPowerpoint($dto);
                break;
            case Dita::class:
                $this->setDita($dto);
                break;
        }
    }

    /**
     * Hydrate all known DTOs from the provided associative array.
     * Values may be arrays or JSON-encoded strings.
     *
     * @param array $json Input data keyed by dto name.
     *
     * @return void
     */
    public function hydrateAllDto(array $json): void
    {
        if (isset($json[ 'json' ])) {
            is_array($json[ 'json' ]) ? $this->hydrateDtoFromArray(Json::class, $json[ 'json' ]) : $this->hydrateDtoFromArray(Json::class, json_decode($json[ 'json' ], true));
        }

        // xml
        if (isset($json[ 'xml' ])) {
            is_array($json[ 'xml' ]) ? $this->hydrateDtoFromArray(Xml::class, $json[ 'xml' ]) : $this->hydrateDtoFromArray(Xml::class, json_decode($json[ 'xml' ], true));
        }

        // yaml
        if (isset($json[ 'yaml' ])) {
            is_array($json[ 'yaml' ]) ? $this->hydrateDtoFromArray(Yaml::class, $json[ 'yaml' ]) : $this->hydrateDtoFromArray(Yaml::class, json_decode($json[ 'yaml' ], true));
        }

        // ms excel
        if (isset($json[ 'ms_excel' ])) {
            is_array($json[ 'ms_excel' ]) ? $this->hydrateDtoFromArray(MSExcel::class, $json[ 'ms_excel' ]) : $this->hydrateDtoFromArray(MSExcel::class, json_decode($json[ 'ms_excel' ], true));
        }

        // ms word
        if (isset($json[ 'ms_word' ])) {
            is_array($json[ 'ms_word' ]) ? $this->hydrateDtoFromArray(MSWord::class, $json[ 'ms_word' ]) : $this->hydrateDtoFromArray(MSWord::class, json_decode($json[ 'ms_word' ], true));
        }

        // ms powerpoint
        if (isset($json[ 'ms_powerpoint' ])) {
            is_array($json[ 'ms_powerpoint' ]) ? $this->hydrateDtoFromArray(MSPowerpoint::class, $json[ 'ms_powerpoint' ]) : $this->hydrateDtoFromArray(MSPowerpoint::class, json_decode($json[ 'ms_powerpoint' ], true));
        }

        // dita
        if (isset($json[ 'dita' ])) {
            is_array($json[ 'dita' ]) ? $this->hydrateDtoFromArray(Dita::class, $json[ 'dita' ]) : $this->hydrateDtoFromArray(Dita::class, json_decode($json[ 'dita' ], true));
        }
    }

    /**
     * Hydrate the struct from a JSON string and optional user id.
     * Sets default empty DTOs, then fills them from payload if present.
     *
     * @param string   $json JSON string.
     * @param int|null $uid  Optional user id to use if not present in JSON.
     *
     * @return FiltersConfigTemplateStruct
     *
     */
    public function hydrateFromJSON(string $json, ?int $uid = null): FiltersConfigTemplateStruct
    {
        $json = json_decode($json, true);

        if (!isset($json[ 'name' ])) {
            throw new DomainException("Cannot instantiate a new FiltersConfigStruct. Invalid data provided.", 400);
        }

        if (empty($uid) && empty($json[ 'uid' ])) {
            throw new DomainException("Cannot instantiate a new FiltersConfigStruct. Invalid user id provided.", 400);
        }

        $this->uid  = $json[ 'uid' ] ?? $uid;
        $this->name = $json[ 'name' ];

        if (isset($json[ 'id' ])) {
            $this->id = $json[ 'id' ];
        }

        if (isset($json[ 'created_at' ])) {
            $this->created_at = $json[ 'created_at' ];
        }

        if (isset($json[ 'deleted_at' ])) {
            $this->deleted_at = $json[ 'deleted_at' ];
        }

        if (isset($json[ 'modified_at' ])) {
            $this->modified_at = $json[ 'modified_at' ];
        }

        // set defaults
        $this->setJson(new Json());
        $this->setXml(new Xml());
        $this->setYaml(new Yaml());
        $this->setMsExcel(new MSExcel());
        $this->setMsWord(new MSWord());
        $this->setMsPowerpoint(new MSPowerpoint());
        $this->setDita(new Dita());

        $this->hydrateAllDto($json);

        return $this;
    }

    /**
     * Serialize the struct to an array, ready for json_encode().
     *
     * @return array<string,mixed>
     * @throws Exception
     */
    public function jsonSerialize(): array
    {
        return [
                'id'            => $this->id,
                'uid'           => $this->uid,
                'name'          => $this->name,
                'xml'           => $this->xml,
                'yaml'          => $this->yaml,
                'json'          => $this->json,
                'ms_word'       => $this->ms_word,
                'ms_excel'      => $this->ms_excel,
                'ms_powerpoint' => $this->ms_powerpoint,
                'dita'          => $this->dita,
                'created_at'    => DateTimeUtil::formatIsoDate($this->created_at),
                'modified_at'   => DateTimeUtil::formatIsoDate($this->modified_at)
        ];
    }

}
