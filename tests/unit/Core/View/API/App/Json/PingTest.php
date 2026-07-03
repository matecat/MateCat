<?php

namespace Matecat\Core\View\API\App\Json;

use Controller\Abstracts\KleinController;
use Klein\DataCollection\ServerDataCollection;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use View\API\App\Json\Ping;

#[CoversClass(Ping::class)]
class PingTest extends AbstractTest
{
    private function makeController(bool $loggedIn = false, ?UserStruct $user = null): KleinController
    {
        $serverData = new ServerDataCollection(['REQUEST_URI' => '/api/v1/ping']);

        $request = $this->createStub(Request::class);
        $request->method('server')->willReturn($serverData);

        $controller = $this->createStub(KleinController::class);
        $controller->method('getRequest')->willReturn($request);
        $controller->method('isLoggedIn')->willReturn($loggedIn);
        $controller->method('getTimer')->willReturn(0.123);

        if ($loggedIn && $user !== null) {
            $controller->method('getUser')->willReturn($user);
        }

        return $controller;
    }

    public function testRenderReturnsExpectedKeys(): void
    {
        $view   = new Ping($this->makeController());
        $result = $view->render();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('client_ip', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('browser', $result);
        $this->assertArrayHasKey('request_uri', $result);
        $this->assertArrayHasKey('took', $result);
    }

    public function testRenderStatusIsOK(): void
    {
        $view   = new Ping($this->makeController());
        $result = $view->render();

        $this->assertSame('OK', $result['status']);
        $this->assertSame('Pong...', $result['message']);
    }

    public function testRenderNotLoggedInReturnsZeroUid(): void
    {
        $view   = new Ping($this->makeController(false));
        $result = $view->render();

        $this->assertSame(['uid' => 0], $result['user']);
    }

    public function testRenderLoggedInReturnsUserData(): void
    {
        $user             = new UserStruct();
        $user->uid        = 42;
        $user->email      = 'test@example.com';
        $user->first_name = 'Jane';
        $user->last_name  = 'Doe';

        $view   = new Ping($this->makeController(true, $user));
        $result = $view->render();

        $this->assertArrayHasKey('uid', $result['user']);
        $this->assertSame(42, $result['user']['uid']);
        $this->assertSame('test@example.com', $result['user']['email']);
        $this->assertSame('Jane', $result['user']['first_name']);
    }

    public function testRenderTimerIsFloat(): void
    {
        $view   = new Ping($this->makeController());
        $result = $view->render();

        $this->assertIsFloat($result['took']);
    }

    public function testRenderRequestUriIsParsed(): void
    {
        $view   = new Ping($this->makeController());
        $result = $view->render();

        $this->assertIsArray($result['request_uri']);
        $this->assertArrayHasKey('path', $result['request_uri']);
        $this->assertSame('/api/v1/ping', $result['request_uri']['path']);
    }
}
