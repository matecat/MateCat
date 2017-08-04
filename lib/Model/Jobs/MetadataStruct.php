<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 30/12/2016
 * Time: 16:28
 */

namespace Jobs;

use DataAccess_IDaoStruct;

class MetadataStruct extends \DataAccess_AbstractDaoObjectStruct  implements DataAccess_IDaoStruct {
    public $id ;
    public $id_job ;
    public $password ;
    public $key ;
    public $value ;

}
