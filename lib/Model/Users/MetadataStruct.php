<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/12/2016
 * Time: 22:51
 */

namespace Users;

class MetadataStruct extends \DataAccess_AbstractDaoObjectStruct  implements \DataAccess_IDaoStruct {

    public $id ;
    public $uid ;
    public $key ;
    public $value ;

}
