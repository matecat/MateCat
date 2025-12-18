<?php

namespace Model\RemoteFiles;

use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class RemoteFileStruct extends AbstractDaoSilentStruct implements IDaoStruct
{
    public int $id;
    public int $id_file;
    public int $id_job;
    public string $remote_id;
    public bool $is_original;
    public int $connected_service_id;

}
