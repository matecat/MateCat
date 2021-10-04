<?php

namespace Files ;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class FilesPartsStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $id ;
    public $id_file ;
    public $key ;
    public $value ;

}