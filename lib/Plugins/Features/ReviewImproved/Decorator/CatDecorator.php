<?php

namespace Features\ReviewImproved\Decorator ;


class CatDecorator extends \Features\ReviewExtended\Decorator\CatDecorator {

    public function decorate() {
        parent::decorate();

        $this->template->review_type = 'improved';

        if ( $this->controller->isRevision() ) {
            $this->template->showReplaceOptionsInSearch = false ;
        }

    }

}
