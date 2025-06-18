<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 05/11/15
 * Time: 11.56
 */
namespace TemplateDecorator;
use AbstractControllers\IController;
use PHPTALWithAppend;

/**
 * Class AbstractDecorator
 *
 * This class represents the first attempt to move some view logic from the controller.
 * A model was generally not present in controllers, so this class takes the controller
 * itself as parameter, and assigns instance variables to the view as needed.
 *
 */
abstract class AbstractDecorator {

    protected IController $controller;

    /**
     * @var PHPTALWithAppend
     */
    protected PHPTALWithAppend $template;

    public function __construct( IController $controller, PHPTALWithAppend $template = null ) {
        $this->controller = $controller;
        $this->template   = $template;
    }

    public abstract function decorate( ?ArgumentInterface $arguments = null );
}