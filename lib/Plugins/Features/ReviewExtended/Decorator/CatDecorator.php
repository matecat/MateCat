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

        $this->template->review_type = 'extended';
        $this->template->segmentFilterEnabled = true;
        $this->template->showReplaceOptionsInSearch = true ;
//        if ( $this->controller->isRevision() ) {
//            $this->template->footer_show_revise_link    = false;
//            $this->template->footer_show_translate_link = true;
//        } else {
//            $this->template->footer_show_revise_link    = true;
//            $this->template->footer_show_translate_link = false;
//        }
    }

}