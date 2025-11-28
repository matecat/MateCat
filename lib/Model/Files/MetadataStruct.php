<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 09/09/2020
 * Time: 19:35
 */

namespace Model\Files;

use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class MetadataStruct extends AbstractDaoSilentStruct implements IDaoStruct
{
    public ?int   $id             = null;
    public int    $id_project;
    public ?int   $files_parts_id = null;
    public int    $id_file;
    public string $key;
    public string $value;
}
