<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 11/07/2017
 * Time: 17:33
 */

namespace Features\ProjectCompletion;


use DataAccess\AbstractDaoObjectStruct;

class CompletionEventStruct extends AbstractDaoObjectStruct {

    public $uid ;
    public $remote_ip_address ;
    public $source ;
    public $is_review ;

}