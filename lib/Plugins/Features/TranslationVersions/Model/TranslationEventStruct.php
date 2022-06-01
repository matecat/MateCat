<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/02/2018
 * Time: 17:24
 */

namespace Features\TranslationVersions\Model;


use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class TranslationEventStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct  {

    public $id ;
    public $uid ;
    public $id_segment ;
    public $id_job ;
    public $version_number ;
    public $source_page ;
    public $status ;
    public $final_revision ;
    public $create_date ;
    public $time_to_edit ;

}