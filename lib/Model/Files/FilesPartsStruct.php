<?php

namespace Model\Files;

use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class FilesPartsStruct extends AbstractDaoSilentStruct implements IDaoStruct {

    public ?int   $id = null;
    public int    $id_file;
    public string $tag_key;
    public string $tag_value;

}