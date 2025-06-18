<?php

namespace Files;

use DataAccess\AbstractDaoSilentStruct;
use DataAccess\IDaoStruct;

class FilesJobStruct extends AbstractDaoSilentStruct implements IDaoStruct {

    public int $id_file;
    public int $id_job;

}