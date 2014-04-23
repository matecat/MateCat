<?php

/**
 * Created by PhpStorm.
 * User: domenico domenico@translated.net / ostico@gmail.com
 * Date: 15/04/14
 * Time: 15.17
 *
 */
class Shop_ItemHTSQuoteJob extends Shop_AbstractItem {

    protected $__storage = array(
            'id'             => null,
            'quantity'       => 1,
            'name'           => null,
            'hts_pid'        => null,
            'source'         => null,
            'target'         => null,
            'price'          => 0,
            'words'          => 0,
            'show_info'      => null,
            'delivery_date'  => null
    );

    /**
     *
     * Because of compatibility with php 5.2 we can't use late static bindings ( introduced in php 5.3 )
     *
     * So we can't use 'new static' reserved word, we have to use 'new self'
     *
     * Workaround: declare this method as abstract and implement every time equals in the children
     *
     * @param $storage
     *
     * @return mixed
     *
     * @throws LogicException/DomainException
     */
    public static function getInflate( $storage ){
        $obj = new self();
        foreach( $storage as $key => $value ){
            $obj->offsetSet( $key, $value );
        }
        return $obj;
    }

}