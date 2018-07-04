<?php

/**
 * Created by PhpStorm.
 */

/**
 * Generic Cart Container / Manager attached to the session
 *
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 15/04/14
 * Time: 15.01
 *
 */
class Shop_Cart {

    /**
     * Singleton Pool container
     *
     * @var Shop_Cart[]
     */
    protected static $_instance = array();

    /**
     * The cart content storage
     *
     * @var Shop_AbstractItem[]
     */
    protected $cart;

    /**
     * Unique identifier for the cart
     *
     * @var string
     */
    protected $cartName;

    /**
     * Retrieve an instance of Cart identified by $cartName in a pool with singleton pattern
     *
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

    /**
     * Create a new instance of cart identified by $cartName
     * That instance is automatically attached to session vars
     *
     * @param $cartName
     */
    protected function __construct( $cartName ) {
        $this->cartName = $cartName;
        if ( !isset ( $_SESSION[ $this->cartName ] ) ) {
            $_SESSION[ $this->cartName ] = array();
        }
        $this->cart =& $_SESSION[ $this->cartName ];
    }

    /**
     * Add an item to the cart
     *
     * @param Shop_AbstractItem $item
     *
     * @throws LogicException
     */
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

    /**
     * Check if an item exists in cart by check it's unique id
     *
     * @param $item_id
     *
     * @return bool
     */
    public function itemExists( $item_id ){
        return array_key_exists( $item_id, $this->cart );
    }

    /**
     * Count items in cart
     *
     * @return int
     */
    public function countItems() {
        return count( $this->cart );
    }

    /**
     * Gat an item from cart bay it's unique id
     *
     * @param $item_id
     *
     * @return mixed
     */
    public function getItem( $item_id ){
        if( array_key_exists( $item_id, $this->cart ) ){

            $classType = $this->cart[ $item_id ][ '_id_type_class' ];

            //for compatibility with php 5.2 ( lacks of late static bindings -> new static() in the abstract class )
            //we can't use Shop_AbstractItem::getInflate to get the right child object
            //return $classType::getInflate( $this->cart[ $item_id ] ); //not valid in php 5.2.x
            return call_user_func_array( $classType . '::getInflate', array( $this->cart[ $item_id ] ) );

        }
    }

    /**
     * Remove an item from the cart
     *
     * @param $item_id
     */
    public function delItem( $item_id ) {
        foreach ( $this->cart as $key => $item ) {
            if ( strpos( $item[ 'id' ], $item_id ) !== false ) {
                unset ( $this->cart[ $key ] );
            }
        }
    }


    /**
     * Clean cart content by removing all items
     *
     */
    public function emptyCart() {
        array_splice( $this->cart, 0 );
    }

    /**
     * Destroy the cart resource
     *
     */
    public function deleteCart() {
        unset ( $this->cart );
        unset ( $_SESSION[ $this->cartName ] );
        unset( self::$_instance[ $this->cartName ]);
    }

    /**
     * Return the cart content
     *
     * @return Shop_AbstractItem[]
     */
    public function getCart() {
        $_cart = $this->cart;
        foreach( $_cart as $k => $v ){
            unset( $_cart[$k]['_id_type_class'] );
        }
        return $_cart;
    }

    /**
     * Check if cart exists by it's name
     *
     * @param $cart_name
     *
     * @return bool
     */
    public static function issetCart( $cart_name ) {
        if ( empty( $_SESSION[ $cart_name ] ) ) {
            false;
        }

        return true;
    }

}