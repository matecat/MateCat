<?php

namespace Matecat\Core\Controllers;

use Controller\Exceptions\RenderTerminatedException;
use Controller\Views\SignInController;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

/**
 * Sentinel exception thrown by the stub instead of exercising the real
 * header()/exit side effects of BaseKleinViewController::redirectToWantedUrl().
 */
class SignInRedirectToWantedUrlSimulatedException extends RuntimeException
{
}

class TestableSignInController extends SignInController
{
    public function __construct()
    {
    }

    public string $lastTemplate = '';
    public bool $redirectToWantedUrlWasCalled = false;

    public function setView(string $template_name, array $params = [], int $code = 200): void
    {
        $this->lastTemplate = $template_name;
    }

    public function redirectToWantedUrl(): never
    {
        $this->redirectToWantedUrlWasCalled = true;
        throw new SignInRedirectToWantedUrlSimulatedException();
    }

    public function render(?int $code = null): never
    {
        throw new RenderTerminatedException();
    }
}

class SignInViewControllerTest extends AbstractTest
{
    private ReflectionClass $reflector;
    private TestableSignInController $controller;

    /** @throws ReflectionException */
    protected function setUp(): void
    {
        parent::setUp();

        $this->reflector = new ReflectionClass(TestableSignInController::class);
        $this->controller = $this->reflector->newInstanceWithoutConstructor();

        unset($_SESSION['wanted_url']);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['wanted_url']);
        parent::tearDown();
    }

    /** @throws ReflectionException */
    private function setLoggedIn(bool $isLogged): void
    {
        $this->reflector->getProperty('userIsLogged')->setValue($this->controller, $isLogged);
    }

    /** @throws ReflectionException */
    #[Test]
    public function renderViewRedirectsToWantedUrlWhenLoggedInAndWantedUrlIsSet(): void
    {
        $this->setLoggedIn(true);
        $_SESSION['wanted_url'] = 'some/previous/page';

        try {
            $this->controller->renderView();
            $this->fail('Expected SignInRedirectToWantedUrlSimulatedException');
        } catch (SignInRedirectToWantedUrlSimulatedException) {
            $this->assertTrue($this->controller->redirectToWantedUrlWasCalled);
            $this->assertSame('', $this->controller->lastTemplate);
        }
    }

    /** @throws ReflectionException */
    #[Test]
    public function renderViewSetsSigninTemplateWhenNotLoggedIn(): void
    {
        $this->setLoggedIn(false);

        try {
            $this->controller->renderView();
            $this->fail('Expected RenderTerminatedException');
        } catch (RenderTerminatedException) {
            $this->assertSame('signin.html', $this->controller->lastTemplate);
            $this->assertFalse($this->controller->redirectToWantedUrlWasCalled);
        }
    }

    /** @throws ReflectionException */
    #[Test]
    public function renderViewSetsSigninTemplateWhenLoggedInButNoWantedUrl(): void
    {
        $this->setLoggedIn(true);
        unset($_SESSION['wanted_url']);

        try {
            $this->controller->renderView();
            $this->fail('Expected RenderTerminatedException');
        } catch (RenderTerminatedException) {
            $this->assertSame('signin.html', $this->controller->lastTemplate);
            $this->assertFalse($this->controller->redirectToWantedUrlWasCalled);
        }
    }
}
