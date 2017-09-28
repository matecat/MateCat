<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 14/07/14
 * Time: 12.43
 */
class badConfigurationController extends viewController {

    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     */
    function doAction() {
        parent::makeTemplate( "badConfiguration.html" );
    }

    /**
     * tell the children to set the template vars
     *
     * @return mixed
     */
    function setTemplateVars() {}
    
}