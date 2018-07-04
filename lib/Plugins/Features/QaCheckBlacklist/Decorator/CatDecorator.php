<?php

namespace Features\QaCheckBlacklist\Decorator ;

class CatDecorator extends \AbstractDecorator {

    /**
     * @var \catController
     */
    protected $controller ;

    /**
     * decorate
     *
     * Adds properties to the view based on the input controller.
     */
    public function decorate() {
        $this->template->qa_check_blacklist_enabled = true ;
    }
}
