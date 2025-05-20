<?php

namespace Files;

use \DataAccess\AbstractDaoSilentStruct;
use \DataAccess\IDaoStruct;

class FilesPartsStruct extends AbstractDaoSilentStruct implements IDaoStruct {

    public $id;
    public $id_file;
    public $key;
    public $value;

}