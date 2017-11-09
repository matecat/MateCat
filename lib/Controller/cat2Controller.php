<?php

/**
 * Created by PhpStorm.
 * User: riccio
 * Date: 24/10/17
 * Time: 16.16
 */
class cat2Controller extends catController
{
    protected $templateName = "revise.html";

    public function setTemplateVars() {
        parent::setTemplateVars();
        $this->template->review_type = "extended";
    }
}