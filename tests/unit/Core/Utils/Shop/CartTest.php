<?php

namespace Matecat\Core\Utils\Shop;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Utils\Session\ArraySessionStore;
use Utils\Shop\Cart;
use Utils\Shop\ItemHTSQuoteJob;

class CartTest extends AbstractTest
{
    private function makeItem(string $id): ItemHTSQuoteJob
    {
        $item = new ItemHTSQuoteJob();
        $item['id'] = $id;
        $item['quantity'] = 1;
        $item['price'] = 10;

        return $item;
    }

    #[Test]
    public function emptyCartLeavesNothingBehind(): void
    {
        $cart = new Cart('test_cart', new ArraySessionStore());
        $cart->addItem($this->makeItem('job-1'));
        $cart->addItem($this->makeItem('job-2'));

        $this->assertTrue($cart->itemExists('job-1'));

        $cart->emptyCart();

        // the cart is keyed by item id, so clearing it has to drop the keys and not merely
        // reindex them, or the ids would stop resolving while the contents were still there
        $this->assertFalse($cart->itemExists('job-1'));
        $this->assertFalse($cart->itemExists('job-2'));
        $this->assertSame([], $cart->getCart());
    }

    #[Test]
    public function emptyCartIsWrittenThroughToTheStore(): void
    {
        $store = new ArraySessionStore();
        $cart = new Cart('test_cart', $store);
        $cart->addItem($this->makeItem('job-1'));

        $cart->emptyCart();

        $this->assertSame([], $store->get('test_cart'));
    }
}
