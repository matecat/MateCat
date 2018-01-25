<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/18/16
 * Time: 4:57 PM
 */
abstract class AbstractModelViewDecorator {

    protected $model;

    public function __construct( $model ) {
        $this->model = $model;
    }

    /**
     * keep this untyped.
     *
     * @param $template
     *
     * @return mixed
     */

    abstract public function decorate( $template ) ;

    public function setTempalteVarsBefore( $template ) {
        $template->googleDriveEnabled =  Bootstrap::isGDriveConfigured();
    }

    public function setTemplateVarsAfter( $template ) {

    }
}