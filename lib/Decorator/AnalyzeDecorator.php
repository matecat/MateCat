<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 04/04/2018
 * Time: 12:39
 */

class AnalyzeDecorator {

    private $controller;

    /**
     * @var PHPTALWithAppend
     */
    private $template;

    public function __construct( analyzeController $controller, PHPTAL $template ) {

        $this->controller = $controller;
        $this->template   = $template;

    }


    public function decorate() {
        $this->template->build_number = INIT::$BUILD_NUMBER;
        $this->template->support_mail = INIT::$SUPPORT_MAIL;

        $this->template->split_enabled    = true;
        $this->template->enable_outsource = true;

    }

}
