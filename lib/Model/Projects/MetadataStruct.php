<?php

namespace Model\Projects;

use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class MetadataStruct extends AbstractDaoSilentStruct implements IDaoStruct
{
    public ?int $id = null;
    public int $id_project;
    public string $key;
    public mixed $value;
}
