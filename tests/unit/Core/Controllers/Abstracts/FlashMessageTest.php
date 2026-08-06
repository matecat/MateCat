<?php

namespace Matecat\Core\Controllers\Abstracts;

use Controller\Abstracts\FlashMessage;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Utils\Session\ArraySessionStore;

/**
 * No session_start(), and no $_SESSION cleanup in setUp()/tearDown(): the store is injected, so each
 * test owns its own state and cannot leak into the next one. That removal is the point of the seam,
 * not a tidy-up — the old fixture had to start a real PHP session to reach a static method.
 */
#[CoversClass(FlashMessage::class)]
class FlashMessageTest extends AbstractTest
{
    private ArraySessionStore $session;
    private FlashMessage $flash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = new ArraySessionStore();
        $this->flash = new FlashMessage($this->session);
    }

    #[Test]
    public function flushReturnsNullWhenNoMessages(): void
    {
        $this->assertNull($this->flash->flush());
    }

    #[Test]
    public function setAddsWarningByDefault(): void
    {
        $this->flash->set('test_key', 'test_value');
        $result = $this->flash->flush();

        $this->assertIsArray($result);
        $this->assertArrayHasKey(FlashMessage::WARNING, $result);
        $this->assertCount(1, $result[FlashMessage::WARNING]);
        $this->assertSame('test_key', $result[FlashMessage::WARNING][0]['key']);
        $this->assertSame('test_value', $result[FlashMessage::WARNING][0]['value']);
    }

    #[Test]
    public function setAddsToSpecifiedType(): void
    {
        $this->flash->set('err_key', 'err_val', FlashMessage::ERROR);
        $result = $this->flash->flush();

        $this->assertIsArray($result);
        $this->assertArrayHasKey(FlashMessage::ERROR, $result);
        $this->assertSame('err_key', $result[FlashMessage::ERROR][0]['key']);
    }

    #[Test]
    public function flushClearsMessages(): void
    {
        $this->flash->set('k', 'v');
        $this->flash->flush();

        $this->assertNull($this->flash->flush());
    }

    #[Test]
    public function multipleMessagesAccumulate(): void
    {
        $this->flash->set('k1', 'v1');
        $this->flash->set('k2', 'v2');
        $result = $this->flash->flush();

        $this->assertIsArray($result);
        $this->assertCount(2, $result[FlashMessage::WARNING]);
    }

    /**
     * The flush must clear the key itself, not merely return a copy — a message that survived its
     * own read would be rendered again on the next page.
     */
    #[Test]
    public function flushRemovesTheKeyFromTheStore(): void
    {
        $this->flash->set('k', 'v');
        $this->assertTrue($this->session->has(FlashMessage::KEY));

        $this->flash->flush();

        $this->assertFalse($this->session->has(FlashMessage::KEY));
    }

    /**
     * Two instances over one store are the same mailbox: controllers build a FlashMessage wherever
     * they need one rather than threading a single object through, so a message set by one must be
     * readable by the next.
     */
    #[Test]
    public function instancesSharingAStoreShareTheMessages(): void
    {
        (new FlashMessage($this->session))->set('k', 'v');

        $result = (new FlashMessage($this->session))->flush();

        $this->assertIsArray($result);
        $this->assertSame('k', $result[FlashMessage::WARNING][0]['key']);
    }
}
