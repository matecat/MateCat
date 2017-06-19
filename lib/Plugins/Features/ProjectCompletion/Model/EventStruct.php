<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 17/05/2017
 * Time: 15:42
 */

namespace Features\ProjectCompletion\Model;

use DataAccess_AbstractDaoObjectStruct ;


class EventStruct extends DataAccess_AbstractDaoObjectStruct {
    public $is_review ;
    public $uid ;
    public $remote_ip_address ;
    public $source ;
}