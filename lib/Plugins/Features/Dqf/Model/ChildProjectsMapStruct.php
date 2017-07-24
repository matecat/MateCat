<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 11/07/2017
 * Time: 15:25
 */

namespace Features\Dqf\Model;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class ChildProjectsMapStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {
    public $id ;
    public $id_job ;

    public $first_segment ;
    public $last_segment ;
    public $password ;

    public $dqf_project_id ;
    public $dqf_project_uuid ;
    public $dqf_parent_uuid ;

    public $archive_date ;
    public $create_date ;
}