<?php

namespace Matecat\Core\Controller\Views;

use Controller\Abstracts\KleinController;
use Controller\Views\CustomPageView;
use Klein\App;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;

class CustomPageViewTest extends AbstractTest
{
    /**
     * CustomPageView's constructor injects the database by registering a
     * `getDatabase` provider on a Klein App and handing that App to the parent
     * controller. This verifies the resulting resolution contract — the injected
     * IDatabase is what `getDatabase()` returns — without running the heavy
     * KleinController constructor (session/user/feature-set init), which is not
     * exercisable as a unit.
     */
    #[Test]
    public function resolves_injected_database_from_klein_app(): void
    {
        $database = $this->createStub(IDatabase::class);

        $app = new App();
        $app->register('getDatabase', fn() => $database);

        $view = (new ReflectionClass(CustomPageView::class))->newInstanceWithoutConstructor();

        $appProp = new ReflectionProperty(KleinController::class, 'app');
        $appProp->setValue($view, $app);

        $this->assertSame($database, $view->getDatabase());
    }
}
