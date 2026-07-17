<?php

namespace Model\Files;

use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class FilesPartsStruct extends AbstractDaoSilentStruct implements IDaoStruct
{

    public ?int $id = null;
    public ?int $id_file = null;
    public ?string $tag_key = null;
    public ?string $tag_value = null;

}