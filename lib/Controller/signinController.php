<?php

class signinController extends viewController {

    /**
     * @inheritDoc
     */
    function doAction()
    {
        parent::makeTemplate( 'signin.html' );
    }

    /**
     * @inheritDoc
     */
    function setTemplateVars()
    {
    }
}