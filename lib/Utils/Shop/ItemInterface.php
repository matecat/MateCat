<?php
/**
 * Created by PhpStorm.
 */

namespace Utils\Shop;

/**
 * Interface implemented in abstract class AbstractItem
 *
 * @see    AbstractItem
 *
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 23/04/14
 * Time: 10.34
 *
 */
interface ItemInterface
{


    /**
     *
     * Because of compatibility with php 5.2 we can't use late static bindings in the abstract class ( introduced in php 5.3 )
     *
     * So we can't use 'static' reserved word, we have to use 'self'
     *
     * Workaround: declare this method into an interface, don't implement it in the abstract class
     * and declare real method every time in the same manner into the children
     *
     * @param $storage
     *
     * @return mixed
     * @see Shop_ItemJob::getInflate
     *
     */
    public static function getInflate($storage): AbstractItem;


} 