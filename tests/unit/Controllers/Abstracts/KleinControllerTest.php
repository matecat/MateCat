<?php

namespace unit\Controllers\Abstracts;

use Controller\Abstracts\KleinController;
use Klein\Request;
use Klein\Response;
use Model\FeaturesBase\FeatureSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversClass(KleinController::class)]
class KleinControllerTest extends TestCase
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
        $newFeatureSet = new FeatureSet();

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

    private function createController(): KleinController
    {
        $request = Request::createFromGlobals();
        $response = new Response();

        return new class ($request, $response) extends KleinController {
            protected bool $useSession = false;

            protected function identifyUser(?bool $useSession = true): void
            {
                // Skip authentication in tests
                $this->userIsLogged = false;
            }
        };
    }
}
