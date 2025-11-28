<?php

/**
 * Created by PhpStorm.
 */

namespace Utils\Shop;

use LogicException;

/**
 * Generic Cart Container / Manager attached to the session
 *
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 15/04/14
 * Time: 15.01
 *
 */
class Cart
{

    /**
     * Singleton Pool container
     *
     * @var Cart[]
     */
    protected static array $_instance = [];

    /**
     * The cart content storage
     *
     * @var array
     */
    protected array $cart;

    /**
     * Unique identifier for the cart
     *
     * @var string
     */
    protected string $cartName;

    /**
     * Retrieve an instance of Cart identified by $cartName in a pool with singleton pattern
     *
     * @param String $cartName
     *
     * @return Cart
     */
    public static function getInstance(string $cartName): Cart
    {
        if (!array_key_exists($cartName, self::$_instance)) {
            self::$_instance[ $cartName ] = new Cart($cartName);
        }

        return self::$_instance[ $cartName ];
    }

    /**
     * Create a new instance of cart identified by $cartName
     * That instance is automatically attached to session vars
     *
     * @param string $cartName
     */
    protected function __construct(string $cartName)
    {
        $this->cartName = $cartName;
        if (!isset ($_SESSION[ $this->cartName ])) {
            $_SESSION[ $this->cartName ] = [];
        }
        $this->cart =& $_SESSION[ $this->cartName ];
    }

    /**
     * Add an item to the cart
     *
     * @param AbstractItem $item
     *
     * @throws LogicException
     */
    public function addItem(AbstractItem $item): void
    {
        if (!isset($item[ 'id' ]) || $item[ 'id' ] == null) {
            throw new LogicException("Field 'id' in object " . get_class($item) . " is mandatory.");
        }

        if (!isset($item[ 'quantity' ]) || $item[ 'quantity' ] == null) {
            throw new LogicException("Field 'quantity' in object " . get_class($item) . " is mandatory.");
        }

        if (!isset($item[ 'price' ]) || $item[ 'price' ] == null) {
            throw new LogicException("Field 'price' in object " . get_class($item) . " is mandatory.");
        }

        $item_id = $item[ 'id' ];

        $Add = true;
        foreach ($this->cart as $key => $_item) {
            if ($_item[ 'id' ] == $item_id) {
                $this->cart[ $key ][ 'quantity' ] += (int)$item[ 'quantity' ];
                $this->cart[ $key ][ 'price' ]    += floatval($item[ 'price' ]);
                $Add                              = false;
            }
        }

        if ($Add) {
            $this->cart[ $item_id ] = $item->getStorage();
        }
    }

    /**
     * Check if an item exists in cart by check it's unique id
     *
     * @param string $item_id
     *
     * @return bool
     */
    public function itemExists(string $item_id): bool
    {
        return array_key_exists($item_id, $this->cart);
    }

    /**
     * Count items in cart
     *
     * @return int
     */
    public function countItems(): int
    {
        return count($this->cart);
    }

    /**
     * Gat an item from cart bay it's unique id
     *
     * @param string $item_id
     *
     * @return ?AbstractItem
     */
    public function getItem(string $item_id): ?AbstractItem
    {
        if (array_key_exists($item_id, $this->cart)) {
            return AbstractItem::getInflate($this->cart[ $item_id ]);
        }

        return null;
    }

    /**
     * Remove an item from the cart
     *
     * @param string $item_id
     */
    public function delItem(string $item_id): void
    {
        foreach ($this->cart as $key => $item) {
            if (str_contains($item[ 'id' ], $item_id)) {
                unset ($this->cart[ $key ]);
            }
        }
    }


    /**
     * Clean cart content by removing all items
     *
     */
    public function emptyCart(): void
    {
        array_splice($this->cart, 0);
    }

    /**
     * Destroy the cart resource
     *
     */
    public function deleteCart(): void
    {
        unset ($this->cart);
        unset ($_SESSION[ $this->cartName ]);
        unset(self::$_instance[ $this->cartName ]);
    }

    /**
     * Return the cart content
     *
     * @return AbstractItem[]
     */
    public function getCart(): array
    {
        $_cart = $this->cart;
        foreach ($_cart as $k => $v) {
            unset($_cart[ $k ][ '_id_type_class' ]);
        }

        return $_cart;
    }

    /**
     * Check if cart exists by it's name
     *
     * @param string $cart_name
     *
     * @return bool
     */
    public static function issetCart(string $cart_name): bool
    {
        if (empty($_SESSION[ $cart_name ])) {
            return false;
        }

        return true;
    }

}