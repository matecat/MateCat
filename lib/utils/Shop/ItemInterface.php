<?php
/**
 * Created by PhpStorm.
 * User: domenico domenico@translated.net / ostico@gmail.com
 * Date: 23/04/14
 * Time: 10.34
 * 
 */

interface Shop_ItemInterface {


    /**
     *
     * Because of compatibility with php 5.2 we can't use late static bindings ( introduced in php 5.3 )
     *
     * So we can't use 'static' reserved word, we have to use 'self'
     *
     * Workaround: declare an interface, implement this in an abstract class
     * and declare real method every time in the children
     *
     * @see Shop_ItemJob::getInflate
     *
     * @param $storage
     *
     * @return mixed
     */
    public static function getInflate( $storage );


} 