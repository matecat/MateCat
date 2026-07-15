<?php

namespace Matecat\Core\Controllers;

use Controller\Exceptions\RenderTerminatedException;
use Controller\Views\XliffToTargetController;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionException;

class TestableXliffToTargetController extends XliffToTargetController
{
    public function __construct()
    {
    }

    public string $lastTemplate = '';

    public function setView(string $template_name, array $params = [], int $code = 200): void
    {
        $this->lastTemplate = $template_name;
    }

    public function render(?int $code = null): never
    {
        throw new RenderTerminatedException();
    }
}

class XliffToTargetViewControllerTest extends AbstractTest
{
    /** @throws ReflectionException */
    #[Test]
    public function renderViewSetsXliffToTargetTemplateAndTerminatesRendering(): void
    {
        $reflector = new ReflectionClass(TestableXliffToTargetController::class);
        /** @var TestableXliffToTargetController $controller */
        $controller = $reflector->newInstanceWithoutConstructor();

        try {
            $controller->renderView();
            $this->fail('Expected RenderTerminatedException');
        } catch (RenderTerminatedException) {
            $this->assertSame('xliffToTarget.html', $controller->lastTemplate);
        }
    }
}
