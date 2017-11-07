<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 05/11/15
 * Time: 11.56
 */
use AbstractControllers\IController;

/**
 * Class AbstractDecorator
 *
 * This class represents the first attempt to move some view logic from the controller.
 * A model was generally not present in controllers, so this class takes the controller
 * itself as parameter, and assigns instance variables to the view as needed.
 *
 * Newer controller implementation make use of a model, making this implementation obsolete.
 *
 * @see AbstractViewModelDecorator
 *
 */
abstract class AbstractDecorator {
    protected $controller;

    /**
     * @var PHPTAL
     */
    protected $template;

    public function __construct( IController $controller, PHPTAL $template = null ) {
        $this->controller = $controller;
        $this->template   = $template;
    }

    public abstract function decorate();
}