<?php

/**
 * Created by PhpStorm.
 * User: domenico domenico@translated.net / ostico@gmail.com
 * Date: 15/04/14
 * Time: 15.01
 *
 */


require 'ItemHTSQuoteJob.php';

class Shop_Cart {

    protected static $_instance = array();

    /**
     * @var Shop_AbstractItem[]
     */
    protected $cart;

    /**
     * @var string
     */
    protected $cartName;

    /**
     * @param String $cartName
     *
     * @return Shop_Cart
     */
    public static function getInstance( $cartName ) {
        if ( !array_key_exists( $cartName, self::$_instance ) ) {
            self::$_instance[ $cartName ] = new Shop_Cart( $cartName );
        }

        return self::$_instance[ $cartName ];
    }

    protected function __construct( $cartName ) {
        $this->cartName = $cartName;
        if ( !isset ( $_SESSION[ $this->cartName ] ) ) {
            $_SESSION[ $this->cartName ] = array();
        }
        $this->cart = & $_SESSION[ $this->cartName ];
    }

    public function addItem( Shop_AbstractItem $item ) {

        if( !isset( $item['id'] ) || $item['id'] == null ){
            throw new LogicException( "Field 'id' in object " . get_class( $item ) . " is mandatory." );
        }

        if( !isset( $item['quantity'] ) || $item['quantity'] == null ){
            throw new LogicException( "Field 'quantity' in object " . get_class( $item ) . " is mandatory." );
        }

        if( !isset( $item['price'] ) || $item['price'] == null ){
            throw new LogicException( "Field 'price' in object " . get_class( $item ) . " is mandatory." );
        }

        $item_id   = $item['id'];

        $Add  = true;
        foreach ( $this->cart as $key => $_item ) {
            if ( $_item[ 'id' ] == $item_id ) {
                $this->cart[ $key ][ 'quantity' ] += (int)$item['quantity'];
                $this->cart[ $key ][ 'price' ] += floatval( $item['price'] );
                $Add = false;
            }
        }

        if ( $Add ) {
            $this->cart[ $item_id ] = $item->getStorage();
        }

    }

    public function itemExists( $item_id ){
        return array_key_exists( $item_id, $this->cart );
    }

    public function countItems() {
        return count( $this->cart );
    }

    public function getItem( $item_id ){
        if( array_key_exists( $item_id, $this->cart ) ){

            $classType = $this->cart[ $item_id ][ '_id_type_class' ];

            //for compatibility with php 5.2 ( lacks of late static bindings -> new static() in the abstract class )
            //we can't use Shop_AbstractItem::getInflate to get the right child object
            //return $classType::getInflate( $this->cart[ $item_id ] ); //not valid in php 5.2.x
            return call_user_func_array( $classType . '::getInflate', array( $this->cart[ $item_id ] ) );

        }
    }

    public function delItem( $item_id ) {
        $item_id = intval( $item_id );

        foreach ( $this->cart as $key => $item ) {
            if ( $item[ 'id' ] == $item_id ) {
                unset ( $this->cart[ $key ] );
            }
        }

    }

    public function emptyCart() {
        array_splice( $this->cart, 0 );
    }

    public function deleteCart() {
        unset ( $this->cart );
        unset ( $_SESSION[ $this->cartName ] );
    }

    public function getCart() {
        $_cart = $this->cart;
        foreach( $_cart as $k => $v ){
            unset( $_cart[$k]['_id_type_class'] );
        }
        return $_cart;
    }

    public static function issetCart( $cart_name ) {
        if ( empty( $_SESSION[ $cart_name ] ) ) {
            false;
        }

        return true;
    }

}