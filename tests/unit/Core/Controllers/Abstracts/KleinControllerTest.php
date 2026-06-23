<?php

namespace Matecat\Core\Controllers\Abstracts;

use Model\DataAccess\Database;
use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\Base;
use Klein\App;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;

#[CoversClass(KleinController::class)]
class KleinControllerTest extends AbstractTest
{
    #[Test]
    public function parseIdSegmentReturnsIdAndSplitNum(): void
    {
        $controller = $this->createController();
        $method = new ReflectionMethod($controller, 'parseIdSegment');

        $result = $method->invoke($controller, '123-2');

        $this->assertSame('123', $result['id_segment']);
        $this->assertSame('2', $result['split_num']);
    }

    #[Test]
    public function parseIdSegmentReturnsNullSplitNumWhenNoDelimiter(): void
    {
        $controller = $this->createController();
        $method = new ReflectionMethod($controller, 'parseIdSegment');

        $result = $method->invoke($controller, '456');

        $this->assertSame('456', $result['id_segment']);
        $this->assertNull($result['split_num']);
    }

    #[Test]
    public function parseIdSegmentHandlesMultipleDelimiters(): void
    {
        $controller = $this->createController();
        $method = new ReflectionMethod($controller, 'parseIdSegment');

        $result = $method->invoke($controller, '789-3-extra');

        $this->assertSame('789', $result['id_segment']);
        $this->assertSame('3', $result['split_num']);
    }

    #[Test]
    public function getFeatureSetReturnsNonNullFeatureSet(): void
    {
        $controller = $this->createController();

        $featureSet = $controller->getFeatureSet();

        $this->assertInstanceOf(FeatureSet::class, $featureSet);
    }

    #[Test]
    public function setFeatureSetUpdatesFeatureSet(): void
    {
        $controller = $this->createController();
        $newFeatureSet = new FeatureSet(Database::obtain());

        $result = $controller->setFeatureSet($newFeatureSet);

        $this->assertSame($newFeatureSet, $controller->getFeatureSet());
        $this->assertSame($controller, $result);
    }

    #[Test]
    public function getParamsReturnsArray(): void
    {
        $controller = $this->createController();

        $params = $controller->getParams();

        $this->assertIsArray($params);
    }

    #[Test]
    public function getPutParamsReturnsNullOnEmptyInput(): void
    {
        $controller = $this->createController();

        // php://input is empty in test context
        $result = $controller->getPutParams();

        $this->assertNull($result);
    }

    #[Test]
    public function afterValidateIsCallableAndReturnsVoid(): void
    {
        $controller = $this->createController();
        $method = new ReflectionMethod($controller, 'afterValidate');

        $result = $method->invoke($controller);

        $this->assertNull($result);
    }

    #[Test]
    public function isViewReturnsFalseByDefault(): void
    {
        $this->assertFalse($this->createController()->isView());
    }

    #[Test]
    public function getRequestReturnsRequestInstance(): void
    {
        $controller = $this->createController();
        $this->assertInstanceOf(Request::class, $controller->getRequest());
    }

    #[Test]
    public function appendValidatorReturnsSelf(): void
    {
        $controller = $this->createController();
        $validator = $this->createStub(Base::class);

        $result = (new ReflectionMethod($controller, 'appendValidator'))->invoke($controller, $validator);

        $this->assertSame($controller, $result);
    }

    #[Test]
    public function isJsonRequestReturnsFalseForNonJsonContentType(): void
    {
        $request = new Request([], [], [], ['HTTP_CONTENT_TYPE' => 'text/html']);
        $response = new Response();
        $app = new App();
        $app->register('getDatabase', static fn() => \Model\DataAccess\Database::obtain());
        $controller = new class ($request, $response, null, $app) extends KleinController {
            protected bool $useSession = false;
            protected function identifyUser(?bool $useSession = true): void { $this->userIsLogged = false; }
        };

        $result = (new ReflectionMethod($controller, 'isJsonRequest'))->invoke($controller);

        $this->assertFalse($result);
    }

    #[Test]
    public function isJsonRequestReturnsTrueForJsonContentType(): void
    {
        $request = new Request([], [], [], ['HTTP_CONTENT_TYPE' => 'application/json']);
        $response = new Response();
        $app = new App();
        $app->register('getDatabase', static fn() => \Model\DataAccess\Database::obtain());
        $controller = new class ($request, $response, null, $app) extends KleinController {
            protected bool $useSession = false;
            protected function identifyUser(?bool $useSession = true): void { $this->userIsLogged = false; }
        };

        $result = (new ReflectionMethod($controller, 'isJsonRequest'))->invoke($controller);

        $this->assertTrue($result);
    }

    #[Test]
    public function performValidationsWithNoValidatorsDoesNotThrow(): void
    {
        $controller = $this->createController();
        $controller->performValidations();
        $this->assertTrue(true);
    }

    #[Test]
    public function validateRequestCallsEachValidatorAndClearsList(): void
    {
        $controller = $this->createController();

        $validator = $this->createMock(Base::class);
        $validator->expects($this->once())->method('validate');

        $appendMethod = new ReflectionMethod($controller, 'appendValidator');
        $appendMethod->invoke($controller, $validator);

        $validateMethod = new ReflectionMethod($controller, 'validateRequest');
        $validateMethod->invoke($controller);

        // mock expectation on validate() being called once is the real assertion
    }

    private function createController(): KleinController
    {
        $request = Request::createFromGlobals();
        $response = new Response();
        $app = new App();
        $app->register('getDatabase', static fn() => Database::obtain());

        return new class ($request, $response, null, $app) extends KleinController {
            protected bool $useSession = false;

            protected function identifyUser(?bool $useSession = true): void
            {
                // Skip authentication in tests
                $this->userIsLogged = false;
            }
        };
    }
}
