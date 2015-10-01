<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 01/10/15
 * Time: 13.09
 */
class EditLogDecorator
{
    private $controller;
    private $template;

    public function __construct( controller $controller, PHPTAL $template){
        $this->controller = $controller;
        $this->template = $template;
    }

    public function decorate(){
//        $this->controller->getModel();
    }
}