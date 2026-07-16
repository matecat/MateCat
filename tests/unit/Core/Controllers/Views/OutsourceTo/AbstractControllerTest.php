<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers\Views\OutsourceTo;

use Controller\Views\OutsourceTo\AbstractController;
use Klein\Request;
use LogicException;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RuntimeException;
use Utils\Logger\MatecatLogger;
use Utils\Shop\AbstractItem;
use Utils\Shop\Cart;

// ─── Stubs ────────────────────────────────────────────────────────────────────

/**
 * No-op logger: accepts any message type so tests don't fail on array->string coercion.
 */
class NullMatecatLogger extends MatecatLogger
{
    public function __construct()
    {
    }

    public function debug($message, array $context = []): void
    {
    }
}

/**
 * Testable concrete subclass:
 * - empty ctor bypasses Klein/DB/HTTP constructor chain
 * - createLogger() returns NullMatecatLogger (avoids Monolog array-message coercion error)
 * - createShopCart() returns an injected Cart stub
 * - getInputFiltered() seam returns the injected array
 * - setView()/render() are stubbed to avoid PHPTAL view-stack initialisation
 */
class StubOutsourceController extends AbstractController
{
    private array $filteredInput = [];
    private ?Cart $stubCart = null;

    public function __construct()
    {
    }

    protected function createLogger(): MatecatLogger
    {
        return new NullMatecatLogger();
    }

    protected function createShopCart(): Cart
    {
        return $this->stubCart ?? parent::createShopCart();
    }

    public function setStubCart(Cart $cart): void
    {
        $this->stubCart = $cart;
    }

    public function setFilteredInput(array $input): void
    {
        $this->filteredInput = $input;
    }

    protected function getInputFiltered(array $filterArgs): array
    {
        return $this->filteredInput;
    }

    public function setView(string $template_name, array $params = [], int $code = 200): void
    {
        // no-op: skip view-stack initialisation in unit tests
    }

    public function render(?int $code = null): never
    {
        throw new \RuntimeException('render() reached in test');
    }

    // expose protected methods
    public function callValidateTheRequest(): void
    {
        $this->validateTheRequest();
    }

    public function callInitDependencies(): void
    {
        $this->initDependencies();
    }
}

// ─── Tests ────────────────────────────────────────────────────────────────────

class AbstractControllerTest extends AbstractTest
{
    // ─── validateTheRequest() guard throws ───────────────────────────────────

    #[Test]
    public function validateTheRequest_throws_when_review_order_page_empty(): void
    {
        $ctrl = new StubOutsourceController();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("'review_order_page' can not be EMPTY");

        $ctrl->callValidateTheRequest();
    }

    #[Test]
    public function validateTheRequest_throws_when_tokenName_empty(): void
    {
        $ctrl = new StubOutsourceController();
        $this->setProp($ctrl, 'review_order_page', 'https://example.com/review');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("'tokenName' can not be EMPTY");

        $ctrl->callValidateTheRequest();
    }

    #[Test]
    public function validateTheRequest_throws_when_id_vendor_empty(): void
    {
        $ctrl = new StubOutsourceController();
        $this->setProp($ctrl, 'review_order_page', 'https://example.com/review');
        $this->setProp($ctrl, 'tokenName', 'tok');
        $this->setProp($ctrl, 'id_vendor', null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("'id_vendor' can not be EMPTY");

        $ctrl->callValidateTheRequest();
    }

    #[Test]
    public function validateTheRequest_throws_when_vendor_name_empty(): void
    {
        $ctrl = new StubOutsourceController();
        $this->setProp($ctrl, 'review_order_page', 'https://example.com/review');
        $this->setProp($ctrl, 'tokenName', 'tok');
        $this->setProp($ctrl, 'id_vendor', 1);
        $this->setProp($ctrl, 'vendor_name', null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("'vendor_name' can not be EMPTY");

        $ctrl->callValidateTheRequest();
    }

    // ─── validateTheRequest() happy path (requires seam + Klein\Request) ─────

    #[Test]
    public function validateTheRequest_succeeds_when_all_properties_set(): void
    {
        $ctrl = $this->makeValidController();

        // Should not throw
        $ctrl->callValidateTheRequest();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function initDependencies_delegates_to_validateTheRequest(): void
    {
        $ctrl = $this->makeValidController();

        // Should not throw
        $ctrl->callInitDependencies();

        $this->addToAssertionCount(1);
    }

    // ─── renderView(): failure branch reaches render() (regression: was unreachable) ──

    #[Test]
    public function renderView_renders_failure_page_when_cart_is_empty(): void
    {
        $ctrl = new StubOutsourceController();
        $this->setProp($ctrl, 'data_key_content', 'missing-key');

        $cart = $this->createStub(Cart::class);
        $cart->method('countItems')->willReturn(0);
        $cart->method('getItem')->willReturn(null);
        $ctrl->setStubCart($cart);

        // Empty cart must reach render() WITHOUT building item-dependent vars.
        // Previously setTemplateVars() ran unconditionally and threw LogicException
        // here, making the failure page unreachable. The render() stub throwing
        // proves the failure branch now renders correctly.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('render() reached in test');

        $ctrl->renderView();
    }

    // ─── renderView(): null cart item in success branch => LogicException ────────

    #[Test]
    public function renderView_throws_when_cart_item_is_null(): void
    {
        $ctrl = new StubOutsourceController();
        $this->setProp($ctrl, 'data_key_content', 'missing-key');

        // countItems() > 0 but the keyed item is absent => getItem() returns null
        $cart = $this->createStub(Cart::class);
        $cart->method('countItems')->willReturn(1);
        $cart->method('getItem')->willReturn(null);
        $ctrl->setStubCart($cart);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cart item not found for key: missing-key');

        $ctrl->renderView();
    }

    // ─── renderView(): success branch, standard service ─────────────────────────

    #[Test]
    public function renderView_reaches_render_for_standard_service(): void
    {
        $ctrl = $this->makeSuccessController([
            'id'            => '42-abc-1',
            'currency'      => 'EUR',
            'quote_pid'     => 'qpid-001',
            'price'         => 99.0,
            'typeOfService' => 'standard',
            'delivery'      => '2026-07-01T12:00:00Z',
        ]);

        // render() stub throws — proves the full success path built vars and reached render()
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('render() reached in test');

        $ctrl->renderView();
    }

    // ─── renderView(): success branch, premium service (r_price/r_delivery) ──────

    #[Test]
    public function renderView_reaches_render_for_premium_service(): void
    {
        $ctrl = $this->makeSuccessController([
            'id'            => '42-abc-1',
            'currency'      => 'EUR',
            'quote_pid'     => 'qpid-001',
            'price'         => 80.0,
            'r_price'       => 20.0,
            'typeOfService' => 'premium',
            'delivery'      => '2026-07-01T12:00:00Z',
            'r_delivery'    => '2026-06-30T12:00:00Z',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('render() reached in test');

        $ctrl->renderView();
    }

    // ─── helpers ─────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $itemData
     */
    private function makeSuccessController(array $itemData): StubOutsourceController
    {
        $ctrl = new StubOutsourceController();
        $this->setProp($ctrl, 'tokenAuth', 'tok123');
        $this->setProp($ctrl, 'data_key_content', $itemData['id']);
        $this->setProp($ctrl, 'review_order_page', 'https://example.com/review');
        $this->setProp($ctrl, 'id_vendor', 1);
        $this->setProp($ctrl, 'vendor_name', 'TestVendor');

        $item = $this->createStub(AbstractItem::class);
        $item->method('offsetGet')->willReturnCallback(fn($key) => $itemData[$key] ?? null);
        $item->method('offsetExists')->willReturn(true);

        $cart = $this->createStub(Cart::class);
        $cart->method('countItems')->willReturn(1);
        $cart->method('getItem')->willReturn($item);
        $ctrl->setStubCart($cart);

        return $ctrl;
    }

    private function makeValidController(): StubOutsourceController
    {
        $ctrl = new StubOutsourceController();
        $this->setProp($ctrl, 'review_order_page', 'https://example.com/review');
        $this->setProp($ctrl, 'tokenName', 'tok');
        $this->setProp($ctrl, 'dataKeyName', 'data');
        $this->setProp($ctrl, 'id_vendor', 1);
        $this->setProp($ctrl, 'vendor_name', 'TestVendor');
        $this->setProp($ctrl, 'request', new Request());

        $ctrl->setFilteredInput(['tok' => 'token-abc', 'data' => '42-abc-1']);

        return $ctrl;
    }

    private function setProp(object $obj, string $property, mixed $value): void
    {
        $ref = new ReflectionClass($obj);
        while ($ref !== false && !$ref->hasProperty($property)) {
            $ref = $ref->getParentClass();
        }
        if ($ref === false) {
            throw new RuntimeException("Property {$property} not found");
        }
        $ref->getProperty($property)->setValue($obj, $value);
    }
}
