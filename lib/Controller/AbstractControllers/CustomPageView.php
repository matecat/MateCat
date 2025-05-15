<?php

use AbstractControllers\BaseKleinViewController;
use Klein\Request;
use Klein\Response;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 09/05/2025
 * Time: 12.08
 *
 */
class CustomPageView extends BaseKleinViewController {

    public function __construct() {
        parent::__construct( Request::createFromGlobals(), new Response() );
    }

}