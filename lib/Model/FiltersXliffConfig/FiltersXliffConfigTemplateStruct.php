<?php

namespace FiltersXliffConfig;

use DataAccess_AbstractDaoSilentStruct;
use Date\DateTimeUtil;
use Exception;
use FiltersXliffConfig\Filters\DTO\Json;
use FiltersXliffConfig\Filters\FiltersConfigModel;
use FiltersXliffConfig\Xliff\XliffConfigModel;
use JsonSerializable;

class FiltersXliffConfigTemplateStruct extends DataAccess_AbstractDaoSilentStruct implements JsonSerializable
{
    public $id;
    public $name;
    public $uid;
    public $created_at;
    public $modified_at;
    public $deleted_at;

    /**
     * @var XliffConfigModel
     */
    private $xliff = null;

    /**
     * @var FiltersConfigModel
     */
    private $filters = null;

    /**
     * @param XliffConfigModel $xliff
     */
    public function setXliff(XliffConfigModel $xliff): void
    {
        $this->xliff = $xliff;
    }

    /**
     * @return XliffConfigModel
     */
    public function getXliff()
    {
        return $this->xliff;
    }

    /**
     * @return FiltersConfigModel
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * @param FiltersConfigModel $filters
     */
    public function setFilters(FiltersConfigModel $filters): void
    {
        $this->filters = $filters;
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
            !isset($json['name']) and
            !isset($json['filters']) and
            !isset($json['xliff'])
        ){
            throw new Exception("Cannot instantiate a new FiltersXliffConfigTemplateStruct. Invalid JSON provided.", 403);
        }

        $this->name = $json['name'];

        $filtersConfig = new FiltersConfigModel();
        $xliffConfig = new XliffConfigModel();

        // xliff
        if(!empty($json['xliff'])){}

        // filters
        if(!empty($json['filters'])){

            $filters = $json['filters'];

            // json
            if(isset($filters['json'])){
                $jsonDto = new Json();

                if(isset($filters['json']['extract_arrays'])){
                    $jsonDto->setExtractArrays($filters['json']['extract_arrays']);
                }

                if(isset($filters['json']['escape_forward_slashes'])){
                    $jsonDto->setEscapeForwardSlashes($filters['json']['escape_forward_slashes']);
                }

                if(isset($filters['json']['translate_keys'])){
                    $jsonDto->setTranslateKeys($filters['json']['translate_keys']);
                }

                if(isset($filters['json']['do_not_translate_keys'])){
                    $jsonDto->setDoNotTranslateKeys($filters['json']['do_not_translate_keys']);
                }

                if(isset($filters['json']['context_keys'])){
                    $jsonDto->setContextKeys($filters['json']['context_keys']);
                }

                $filtersConfig->setJson($jsonDto);
            }
        }

        $this->setFilters($filtersConfig);
        $this->setXliff($xliffConfig);

        return $this;
    }

    /**
     * @inheritDoc
     * @throws \Exception
     */
    public function jsonSerialize()
    {
        return [
            'id' => (int)$this->id,
            'uid' => (int)$this->uid,
            'name' => $this->name,
            'filters' => ($this->filters !== null) ? $this->filters : new \stdClass(),
            'xliff' => ($this->xliff !== null) ? $this->xliff : new \stdClass(),
            'createdAt' => DateTimeUtil::formatIsoDate($this->created_at),
            'modifiedAt' => DateTimeUtil::formatIsoDate($this->modified_at),
            'deletedAt' => DateTimeUtil::formatIsoDate($this->deleted_at),
        ];
    }
}