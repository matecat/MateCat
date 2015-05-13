<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 27/01/14
 * Time: 18.55
 * 
 */


abstract class helperController extends controller {

    //this lets the helper issue all the checks which are required before redirecting
    //abstract public performValidation();
    //implement abstract finalize empty
    public function finalize() {

    }

    //redirect the page
    public function redirect($url) {
        header('Location: ' . $url);
    }

}