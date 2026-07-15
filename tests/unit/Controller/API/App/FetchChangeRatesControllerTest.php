<?php

namespace Controller\API\App;

use Controller\API\Commons\Validators\Base;
use Controller\API\Commons\Validators\LoginValidator;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use ReflectionClass;
use ReflectionException;
use Utils\Currency\ChangeRatesFetcher;
use Utils\Currency\TranslatedChangeRatesFetcher;

class FetchChangeRatesControllerTest extends AbstractTest
{

    /**
     * @param object $object
     * @param string $name
     * @param mixed $value
     * @return void
     */
    private function setProp(object $object, string $name, mixed $value): void
    {
        $reflection = new ReflectionClass($object);

        while (!$reflection->hasProperty($name)) {
            $reflection = $reflection->getParentClass();
        }

        $property = $reflection->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * @param object $object
     * @param string $method
     * @param array<mixed> $args
     * @return mixed
     * @throws ReflectionException
     */
    private function callMethod(object $object, string $method, array $args = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $reflectionMethod = $reflection->getMethod($method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs($object, $args);
    }

    public function testFetchReturnsChangeRatesAsJson(): void
    {
        $stubFetcher = $this->createStub(ChangeRatesFetcher::class);
        $stubFetcher->method('getChangeRates')->willReturn('{"EUR":"1.0000","USD":"1.1085"}');

        $controller = new TestableFetchChangeRatesController();
        $controller->setStubFetcher($stubFetcher);

        $this->setProp($controller, 'request', new Request());
        $this->setProp($controller, 'response', new Response());
        $this->setProp($controller, 'database', obtainTestDatabase());

        // fetch() calls Response::json(), which send()s the body to stdout and pollutes
        // the test-runner output. Swallow that echo; the body is still recorded on the
        // Response object, so the assertions below are unaffected.
        ob_start();
        try {
            $controller->fetch();
        } finally {
            ob_end_clean();
        }

        $response = $this->callMethod($controller, 'getResponse');

        self::assertSame(200, $response->code());

        $decoded = json_decode($response->body(), true);

        self::assertIsArray($decoded);
        self::assertSame([], $decoded['errors']);
        self::assertSame(1, $decoded['code']);
        self::assertSame('{"EUR":"1.0000","USD":"1.1085"}', $decoded['data']);
    }

    public function testRegisterValidatorsAppendsLoginValidator(): void
    {
        $controller = new TestableFetchChangeRatesController();

        $this->setProp($controller, 'request', new Request());
        $this->setProp($controller, 'response', new Response());
        $this->setProp($controller, 'database', obtainTestDatabase());

        $this->callMethod($controller, 'registerValidators');

        $validators = $this->callMethod($controller, 'getValidatorsForTest');

        self::assertCount(1, $validators);
        self::assertInstanceOf(\Controller\API\Commons\Validators\LoginValidator::class, $validators[0]);
    }

    /**
     * Exercises the real (non-overridden) getChangeRatesFetcher() seam so the
     * production factory line is covered. Construction is network-free; the
     * outbound call only happens in fetchChangeRates(), which is not invoked.
     *
     * @throws ReflectionException
     */
    public function testGetChangeRatesFetcherReturnsTranslatedImplementation(): void
    {
        $controller = new RealFetchChangeRatesController();

        $fetcher = $this->callMethod($controller, 'getChangeRatesFetcher');

        self::assertInstanceOf(TranslatedChangeRatesFetcher::class, $fetcher);
    }
}

class RealFetchChangeRatesController extends FetchChangeRatesController
{

    public function __construct()
    {
    }
}

class TestableFetchChangeRatesController extends FetchChangeRatesController
{

    private ?ChangeRatesFetcher $stubFetcher = null;

    public function __construct()
    {
    }

    public function setStubFetcher(ChangeRatesFetcher $fetcher): void
    {
        $this->stubFetcher = $fetcher;
    }

    protected function getChangeRatesFetcher(): ChangeRatesFetcher
    {
        return $this->stubFetcher;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * @return array<int, \Controller\API\Commons\Validators\Base>
     */
    public function getValidatorsForTest(): array
    {
        return $this->validators;
    }
}
