<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 27/04/17
 * Time: 14.50
 *
 */

namespace Comments;


use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class OpenThreadsStruct  extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $id_project;
    public $password;
    public $id_job;
    public $count;

}