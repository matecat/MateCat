<?php

namespace Model\Files;

use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class FilesJobStruct extends AbstractDaoSilentStruct implements IDaoStruct
{

    public int $id_file;
    public int $id_job;

}