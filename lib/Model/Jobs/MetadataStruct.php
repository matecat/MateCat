<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 30/12/2016
 * Time: 16:28
 */

namespace Jobs;

use DataAccess\AbstractDaoObjectStruct;
use DataAccess\IDaoStruct;

class MetadataStruct extends AbstractDaoObjectStruct implements IDaoStruct {
    public $id;
    public $id_job;
    public $password;
    public $key;
    public $value;

}
