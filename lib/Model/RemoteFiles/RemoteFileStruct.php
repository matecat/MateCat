<?php

class RemoteFiles_RemoteFileStruct extends \DataAccess\AbstractDaoSilentStruct implements \DataAccess\IDaoStruct {
    public $id;
    public $id_file;
    public $id_job;
    public $remote_id;
    public $is_original;
    public $connected_service_id;

}
