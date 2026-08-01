<?php

/**
 * Created by PhpStorm.
 */

namespace Utils\Shop;

use LogicException;
use RuntimeException;
use Utils\Session\SessionStore;

/**
 * Generic Cart Container / Manager backed by an injected SessionStore
 *
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 15/04/14
 * Time: 15.01
 *
 */
class Cart
{

    /**
     * The cart content storage
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $cart;

    /**
     * Unique identifier for the cart
     *
     * @var string
     */
    protected string $cartName;

    protected SessionStore $session;

    /**
     * Create a cart identified by $cartName, backed by the given session store.
     *
     * The cart is read from the store once here and written back by persist() on every mutation. It
     * used to alias the superglobal — `$this->cart =& $_SESSION[$cartName]` — so mutations persisted
     * implicitly; a SessionStore has no references, so the writes are now explicit. A caller that
     * mutates and does not re-read through a second instance sees no difference.
     *
     * The singleton pool this class used to keep is gone with it. Callers that need one identity per
     * cart name within a request hold the instance themselves, which also stops a per-user cart from
     * living in static state.
     *
     * @param string $cartName
     * @param SessionStore $session
     */
    public function __construct(string $cartName, SessionStore $session)
    {
        $this->cartName = $cartName;
        $this->session  = $session;

        $stored = $session->get($cartName);
        // Nothing written through this class can be a non-array, but the store is untyped and a session
        // outlives any single deploy, so anything else is treated as an empty cart rather than fatal.
        $this->cart = is_array($stored) ? $stored : [];
    }

    private function persist(): void
    {
        $this->session->set($this->cartName, $this->cart);
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
        if (!isset($item['id']) || $item['id'] == null) {
            throw new LogicException("Field 'id' in object " . get_class($item) . " is mandatory.");
        }

        if (!isset($item['quantity']) || $item['quantity'] == null) {
            throw new LogicException("Field 'quantity' in object " . get_class($item) . " is mandatory.");
        }

        if (!isset($item['price']) || $item['price'] == null) {
            throw new LogicException("Field 'price' in object " . get_class($item) . " is mandatory.");
        }

        $item_id = $item['id'];

        $Add = true;
        foreach ($this->cart as $key => $_item) {
            if ($_item['id'] == $item_id) {
                $this->cart[$key]['quantity'] += (int)$item['quantity'];
                $this->cart[$key]['price'] += floatval($item['price']);
                $Add = false;
            }
        }

        if ($Add) {
            $this->cart[$item_id] = $item->getStorage();
        }

        $this->persist();
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
     * @throws RuntimeException
     * @throws LogicException
     */
    public function getItem(string $item_id): ?AbstractItem
    {
        if (array_key_exists($item_id, $this->cart)) {
            return AbstractItem::getInflate($this->cart[$item_id]);
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
            if (str_contains($item['id'], $item_id)) {
                unset ($this->cart[$key]);
            }
        }

        $this->persist();
    }


    /**
     * Clean cart content by removing all items
     *
     */
    public function emptyCart(): void
    {
        array_splice($this->cart, 0);
        $this->persist();
    }

    /**
     * Destroy the cart resource
     *
     */
    public function deleteCart(): void
    {
        // Assigned, not unset: $cart is a typed property, so unsetting it turns every later read on
        // this instance into a fatal "must not be accessed before initialization" rather than an
        // empty cart. The old code could unset because the instance left the singleton pool with it.
        $this->cart = [];
        $this->session->remove($this->cartName);
    }

    /**
     * Return the cart content
     *
     * @return array<string, array<string, mixed>>
     */
    public function getCart(): array
    {
        $_cart = $this->cart;
        foreach ($_cart as $k => $v) {
            unset($_cart[$k]['_id_type_class']);
        }

        return $_cart;
    }

}