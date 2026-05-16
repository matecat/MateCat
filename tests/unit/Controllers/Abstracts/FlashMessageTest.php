<?php

namespace unit\Controllers\Abstracts;

use Controller\Abstracts\FlashMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlashMessage::class)]
class FlashMessageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        unset($_SESSION[FlashMessage::KEY]);
    }

    protected function tearDown(): void
    {
        unset($_SESSION[FlashMessage::KEY]);
        parent::tearDown();
    }

    #[Test]
    public function flushReturnsNullWhenNoMessages(): void
    {
        $this->assertNull(FlashMessage::flush());
    }

    #[Test]
    public function setAddsWarningByDefault(): void
    {
        FlashMessage::set('test_key', 'test_value');
        $result = FlashMessage::flush();

        $this->assertIsArray($result);
        $this->assertArrayHasKey(FlashMessage::WARNING, $result);
        $this->assertCount(1, $result[FlashMessage::WARNING]);
        $this->assertSame('test_key', $result[FlashMessage::WARNING][0]['key']);
        $this->assertSame('test_value', $result[FlashMessage::WARNING][0]['value']);
    }

    #[Test]
    public function setAddsToSpecifiedType(): void
    {
        FlashMessage::set('err_key', 'err_val', FlashMessage::ERROR);
        $result = FlashMessage::flush();

        $this->assertIsArray($result);
        $this->assertArrayHasKey(FlashMessage::ERROR, $result);
        $this->assertSame('err_key', $result[FlashMessage::ERROR][0]['key']);
    }

    #[Test]
    public function flushClearsMessages(): void
    {
        FlashMessage::set('k', 'v');
        FlashMessage::flush();

        $this->assertNull(FlashMessage::flush());
    }

    #[Test]
    public function multipleMessagesAccumulate(): void
    {
        FlashMessage::set('k1', 'v1');
        FlashMessage::set('k2', 'v2');
        $result = FlashMessage::flush();

        $this->assertIsArray($result);
        $this->assertCount(2, $result[FlashMessage::WARNING]);
    }
}
