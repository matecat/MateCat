<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 12/10/17
 * Time: 18.14
 *
 */

if( !@include_once 'inc/Bootstrap.php')
    header("Location: configMissing");

Bootstrap::start();

$mDataDao = new \Users\MetadataDao();

var_dump( $mDataDao->destroyCacheKey( 2611, 'mmt' ) );