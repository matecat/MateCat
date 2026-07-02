<?php

namespace Matecat\Core\View\API\V2\Json;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\CoversClass;
use View\API\V2\Json\WaitCreation;

#[CoversClass(WaitCreation::class)]
class WaitCreationTest extends AbstractTest
{
    public function testRenderReturnsStatusAndMessage(): void
    {
        $view   = new WaitCreation();
        $result = $view->render();

        $this->assertIsArray($result);
        $this->assertSame(202, $result['status']);
        $this->assertSame('Project in queue. Wait.', $result['message']);
    }

    public function testRenderHasExactlyTwoKeys(): void
    {
        $view   = new WaitCreation();
        $result = $view->render();

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('message', $result);
    }
}
