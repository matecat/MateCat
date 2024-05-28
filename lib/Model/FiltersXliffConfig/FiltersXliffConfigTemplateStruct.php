<?php

namespace FiltersXliffConfig;

use DataAccess_AbstractDaoSilentStruct;
use Date\DateTimeUtil;
use FiltersXliffConfig\Filters\FiltersConfigModel;
use FiltersXliffConfig\Xliff\XliffConfigModel;
use JsonSerializable;

class FiltersXliffConfigTemplateStruct extends DataAccess_AbstractDaoSilentStruct implements JsonSerializable
{
    public $id;
    public $uid;
    public $created_at;
    public $modified_at;
    public $deleted_at;

    private $xliff;
    private $filters;

    /**
     * FiltersXliffConfigTemplateStruct constructor.
     * @param XliffConfigModel $xliff
     * @param FiltersConfigModel $filters
     */
    public function __construct(
        XliffConfigModel $xliff,
        FiltersConfigModel $filters
    )
    {
        parent::__construct();
        $this->xliff = $xliff;
        $this->filters = $filters;
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
            'filters' => $this->filters,
            'xliff' => $this->xliff,
            'createdAt' => DateTimeUtil::formatIsoDate($this->created_at),
            'modifiedAt' => DateTimeUtil::formatIsoDate($this->modified_at),
            'deletedAt' => DateTimeUtil::formatIsoDate($this->deleted_at),
        ];
    }
}