<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 09/09/2020
 * Time: 19:35
 */

namespace Files;

use DataAccess\AbstractDaoSilentStruct;
use DataAccess\IDaoStruct;

class MetadataStruct extends AbstractDaoSilentStruct implements IDaoStruct {

    public $id;
    public $id_project;
    public $files_parts_id;
    public $id_file;
    public $key;
    public $value;


}
