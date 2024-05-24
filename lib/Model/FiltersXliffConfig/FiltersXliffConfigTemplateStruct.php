<?php

namespace FiltersXliffConfig;

use DataAccess_AbstractDaoSilentStruct;
use FiltersXliffConfig\Filters\FiltersConfigModel;
use FiltersXliffConfig\Xliff\XliffConfigModel;
use JsonSerializable;

class FiltersXliffConfigTemplateStruct extends DataAccess_AbstractDaoSilentStruct implements JsonSerializable
{
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
     */
    public function jsonSerialize()
    {
        return [
            'filters' => $this->filters,
            'xliff' => $this->xliff,
        ];
    }
}