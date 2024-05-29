<?php

namespace FiltersXliffConfig;

use DataAccess_AbstractDaoSilentStruct;
use Date\DateTimeUtil;
use Exception;
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

    private $xliff = null;
    private $filters = null;

    /**
     * @param XliffConfigModel $xliff
     */
    public function setXliff(XliffConfigModel $xliff): void
    {
        $this->xliff = $xliff;
    }

    /**
     * @return null
     */
    public function getXliff()
    {
        return $this->xliff;
    }

    /**
     * @return null
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

        // object here
        $this->filters = $json['filters'];
        $this->xliff = $json['xliff'];

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