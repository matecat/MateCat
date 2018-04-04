<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 04/04/2018
 * Time: 12:39
 */

use Jobs\JobStatsStruct;
use LexiQA\LexiQADecorator;

class AnalyzeDecorator {

    private $controller;

    /**
     * @var PHPTALWithAppend
     */
    private $template;


    private $lang_handler ;

    public function __construct( analyzeController $controller, PHPTAL $template ) {

        $this->controller     = $controller;
        $this->template       = $template;

        $this->lang_handler = Langs_Languages::getInstance();
    }

    public function decorate() {
        $this->template->build_number = INIT::$BUILD_NUMBER;
        $this->template->support_mail = INIT::$SUPPORT_MAIL;

        $this->template->split_enabled = true;
        $this->template->enable_outsource = true;

    }

}
