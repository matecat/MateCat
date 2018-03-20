<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 10/11/2017
 * Time: 15:33
 */

namespace Features\ReviewExtended\Decorator ;

use Features\ReviewImproved;

class CatDecorator extends ReviewImproved\Decorator\CatDecorator {

    public function decorate() {
        parent::decorate();

        $this->template->review_type = 'extended-footer';
//        $this->template->review_type = 'extended' ;
        $this->template->segmentFilterEnabled = true;
        $this->template->showReplaceOptionsInSearch = true ;
    }

}