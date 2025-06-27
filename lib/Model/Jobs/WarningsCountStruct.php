<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 27/04/17
 * Time: 17.53
 *
 */

namespace Jobs;


use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class WarningsCountStruct extends AbstractDaoSilentStruct implements IDaoStruct {

    public $count;
    public $id_job;
    public $password;
    public $segment_list;

}