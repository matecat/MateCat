<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/07/2017
 * Time: 12:50
 */

namespace Features\Dqf\Model;

use DataAccess_AbstractDaoSilentStruct;

class ExtendedTranslationStruct extends DataAccess_AbstractDaoSilentStruct {
    public $id_segment ;
    public $id_job;

    public $translation_before ;
    public $translation_after ;

    public $time ;

    public $segment_origin ;
    public $suggestion_match ;
}