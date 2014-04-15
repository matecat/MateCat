<?php

/**
 * Created by PhpStorm.
 * User: domenico domenico@translated.net / ostico@gmail.com
 * Date: 15/04/14
 * Time: 15.17
 *
 */
class Shop_Item extends ArrayObject  {

    protected $__storage = array(
            'id'            => null,
            'quantity'      => 1,
            'name'          => null,
            'source'        => null,
            'target'        => null,
            'price'         => 0,
            'info'          => null,
            'delivery_date' => null
    );

    public function __construct(){
        parent::__construct();
        foreach( $this->__storage as $key => $value ){
            $this->offsetSet( $key, $value );
        }
    }

    /**
     * @param $storage
     *
     * @return Shop_Item
     *
     * @throw LogicException|DomainException
     */
    public static function getInflate( $storage ){
        $obj = new self();
        foreach( $storage as $key => $value ){
            $obj->offsetSet( $key, $value );
        }
        return $obj;
    }

    public function getStorage(){
        return $this->getArrayCopy();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     *
     * @param mixed $offset <p>
     *                      The offset to assign the value to.
     *                      </p>
     * @param mixed $value  <p>
     *                      The value to set.
     *                      </p>
     *
     * @return void
     *
     * @throws LogicException/DomainException
     */
    public function offsetSet( $offset, $value ) {

        if ( empty( $offset ) ) {
            throw new LogicException( "Can not assign a value to an EMPTY key." );
        }

        if ( !array_key_exists( $offset, $this->__storage ) ) {
            throw new DomainException( "Field $offset does not exists in " . __CLASS__ . " structure." );
        }

        $value = filter_var( $value, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW );
        parent::offsetSet( $offset, $value );

    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @param mixed $offset <p>
     *                      The offset to unset.
     *                      </p>
     *
     * @return void
     *
     * @throws DomainException
     */
    public function offsetUnset( $offset ) {
        if ( array_key_exists( $offset, $this->__storage ) ) {
            parent::offsetUnset( $offset );
        } else {
            throw new DomainException( "Field $offset does not exists in " . __CLASS__ . " structure." );
        }
    }

} 