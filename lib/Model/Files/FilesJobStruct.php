<?php

namespace Files ;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class FilesJobStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $id_file ;
    public $id_job ;

}