<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 27/04/17
 * Time: 17.53
 *
 */

namespace Jobs;


use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class WarningsCountStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $count;
    public $id_job;
    public $password;
    public $segment_list;

}