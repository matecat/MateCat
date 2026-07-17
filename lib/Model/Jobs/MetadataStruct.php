<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 30/12/2016
 * Time: 16:28
 */

namespace Model\Jobs;

use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\IDaoStruct;

class MetadataStruct extends AbstractDaoObjectStruct implements IDaoStruct
{
    public ?int $id = null;
    public int $id_job;
    public string $password;
    public string $key;
    public mixed $value;
}
