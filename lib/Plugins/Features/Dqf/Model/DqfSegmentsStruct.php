<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/07/2017
 * Time: 12:11
 */

namespace Features\Dqf\Model;

use DataAccess_AbstractDaoSilentStruct;

class DqfSegmentsStruct extends DataAccess_AbstractDaoSilentStruct {
    public $id_segment ;
    public $dqf_segment_id ;
    public $dqf_translation_id ;
}