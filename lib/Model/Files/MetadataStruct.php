<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 09/09/2020
 * Time: 19:35
 */
namespace Files;

use DataAccess_IDaoStruct;

class MetadataStruct extends \DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $id ;
    public $id_project ;
    public $files_parts_id ;
    public $id_file ;
    public $key ;
    public $value ;


}
