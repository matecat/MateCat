<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 05/11/15
 * Time: 11.56
 */
abstract class AbstractDecorator {
    protected $controller;
    protected $template;

    public function __construct( controller $controller, PHPTAL $template ) {
        $this->controller = $controller;
        $this->template   = $template;
    }

    public abstract function decorate();
}