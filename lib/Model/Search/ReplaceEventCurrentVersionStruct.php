<?php

namespace Model\Search;

use Model\DataAccess\ShapelessConcreteStruct;

class ReplaceEventCurrentVersionStruct extends ShapelessConcreteStruct {

    // DATABASE FIELDS
    public ?int $id = null;
    public int $id_job;
    public int $version;
}