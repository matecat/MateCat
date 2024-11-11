<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 11/11/24
 * Time: 13:15
 *
 */

if ( !@include_once 'inc/Bootstrap.php' ) {
    header( "Location: configMissing" );
}

Bootstrap::start();
Bootstrap::sessionStart();

unset($_SESSION);