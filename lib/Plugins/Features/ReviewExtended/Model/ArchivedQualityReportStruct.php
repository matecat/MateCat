<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 17/05/2017
 * Time: 17:42
 */

namespace Features\ReviewExtended\Model;


class ArchivedQualityReportStruct extends \DataAccess_AbstractDaoSilentStruct implements \DataAccess_IDaoStruct {

    public $id ;
    public $created_by ;

    public $id_project ;
    public $id_job ;
    public $password ;

    public $job_first_segment ;
    public $job_last_segment ;
    public $create_date ;
    public $quality_report ;

    public $version ;

}