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
     * @param array<string, mixed> $storage
     */
    public static function getInflate(array $storage): AbstractItem;


} 