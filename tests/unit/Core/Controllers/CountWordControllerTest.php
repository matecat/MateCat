<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Exceptions\ValidationError;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\V3\CountWordController;
use Exception;
use Klein\HttpStatus;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use Utils\LQA\SizeRestriction\SizeRestriction;

class TestableCountWordController extends CountWordController
{
    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }

    protected function registerValidators(): void
    {
    }
}

/**
 * Subclass that allows registerValidators() to run the REAL production code,
 * while still bypassing the real constructor (no Klein App / session needed).
 */
class ValidatorTestableCountWordController extends CountWordController
{
    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }

    // registerValidators() is NOT overridden — the real method runs.
}

/**
 * Testable subclass that stubs the two hard dependencies (filter+size-restriction)
 * so rawWords() can execute in isolation.
 */
class RawWordsTestableCountWordController extends CountWordController
{
    public int $stubbedWordCount = 42;
    public SizeRestriction $stubbedSizeRestriction;

    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }

    protected function registerValidators(): void
    {
    }

    protected function getRawWordsCount(string $text, string $language): int
    {
        return $this->stubbedWordCount;
    }

    /**
     * @throws Exception
     */
    protected function buildSizeRestriction(string $text): SizeRestriction
    {
        return $this->stubbedSizeRestriction;
    }
}

class CountWordControllerTest extends AbstractTest
{
    private ReflectionClass $reflector;
    private TestableCountWordController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new TestableCountWordController();
        $this->reflector = new ReflectionClass(CountWordController::class);
        $this->reflector->getProperty('database')->setValue($this->controller, obtainTestDatabase());
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /**
     * @param array<string, string|null> $params
     */
    private function setRequest(array $params, TestableCountWordController $controller): void
    {
        $request = $this->createStub(Request::class);
        $request->method('param')->willReturnCallback(
            static fn(string $key, mixed $default = null) => $params[$key] ?? $default
        );
        $this->reflector->getProperty('request')->setValue($controller, $request);
    }

    private function setResponse(TestableCountWordController $controller): Response&MockObject
    {
        $mock = $this->createMock(Response::class);
        $mock->method('status')->willReturn($this->createStub(HttpStatus::class));
        $mock->method('json')->willReturnSelf();
        $this->reflector->getProperty('response')->setValue($controller, $mock);
        return $mock;
    }

    // ── getRawWordsCount ─────────────────────────────────────────────────────

    /**
     * @throws Exception
     */
    #[Test]
    public function getRawWordsCount_returns_word_count_for_plain_text(): void
    {
        $method = $this->reflector->getMethod('getRawWordsCount');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, 'hello world', 'en-US');

        self::assertSame(2, $result);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function getRawWordsCount_returns_zero_for_empty_string(): void
    {
        $method = $this->reflector->getMethod('getRawWordsCount');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, '', 'en-US');

        self::assertSame(0, $result);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function getRawWordsCount_returns_count_for_single_word(): void
    {
        $method = $this->reflector->getMethod('getRawWordsCount');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, 'hello', 'en-US');

        self::assertSame(1, $result);
    }

    // ── buildSizeRestriction ─────────────────────────────────────────────────

    /**
     * Exercises the real seam body (MateCatFilter::getInstance + new SizeRestriction)
     * against an empty FeatureSet, which resolves headless without DB/Redis.
     *
     * @throws Exception
     */
    #[Test]
    public function buildSizeRestriction_returns_restriction_for_plain_text(): void
    {
        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class));
        $this->reflector->getProperty('featureSet')->setValue($this->controller, $featureSet);

        $method = $this->reflector->getMethod('buildSizeRestriction');
        $method->setAccessible(true);

        $restriction = $method->invoke($this->controller, 'hello world');

        self::assertInstanceOf(SizeRestriction::class, $restriction);
        self::assertSame(11, $restriction->getCleanedStringLength());
    }

    // ── rawWords ─────────────────────────────────────────────────────────────

    private function buildRawWordsController(): RawWordsTestableCountWordController
    {
        $controller = new RawWordsTestableCountWordController();
        $ref = new ReflectionClass(CountWordController::class);

        // user with email
        $user = new UserStruct();
        $user->email = 'test@example.com';
        $ref->getProperty('user')->setValue($controller, $user);

        // featureSet stub — loadFromUserEmail is a no-op
        $featureSet = $this->createStub(FeatureSet::class);
        $ref->getProperty('featureSet')->setValue($controller, $featureSet);

        // language
        $ref->getProperty('language')->setValue($controller, 'en-US');

        return $controller;
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function rawWords_returns_word_count_and_character_count_without_limit(): void
    {
        $controller = $this->buildRawWordsController();
        $ref = new ReflectionClass(CountWordController::class);

        // request: param('text') returns 'hello world', no ->limit
        $request = $this->createStub(Request::class);
        $request->method('param')->willReturnCallback(
            static fn(string $key, mixed $default = null) => match ($key) {
                'text' => 'hello world',
                default => $default,
            }
        );
        $ref->getProperty('request')->setValue($controller, $request);

        // sizeRestriction stub
        $sizeRestriction = $this->createStub(SizeRestriction::class);
        $sizeRestriction->method('getCleanedStringLength')->willReturn(11);
        $controller->stubbedSizeRestriction = $sizeRestriction;

        // response mock
        $response = $this->createMock(Response::class);
        $response->method('status')->willReturn($this->createStub(HttpStatus::class));
        $response->expects(self::once())
            ->method('json')
            ->with([
                'word_count' => 42,
                'character_count' => ['length' => 11],
            ]);
        $ref->getProperty('response')->setValue($controller, $response);

        $controller->rawWords();
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function rawWords_includes_limit_fields_when_limit_is_numeric(): void
    {
        $controller = $this->buildRawWordsController();
        $ref = new ReflectionClass(CountWordController::class);

        // Use a real Klein Request so isset($request->limit) works via __isset/__get magic
        $request = new Request(
            ['text' => 'hello world', 'limit' => '100'],  // params_get
        );
        $ref->getProperty('request')->setValue($controller, $request);

        // sizeRestriction stub
        $sizeRestriction = $this->createStub(SizeRestriction::class);
        $sizeRestriction->method('getCleanedStringLength')->willReturn(11);
        $sizeRestriction->method('checkLimit')->willReturn(true);
        $sizeRestriction->method('getCharactersRemaining')->willReturn(89);
        $controller->stubbedSizeRestriction = $sizeRestriction;

        // response mock
        $response = $this->createMock(Response::class);
        $response->method('status')->willReturn($this->createStub(HttpStatus::class));
        $response->expects(self::once())
            ->method('json')
            ->with([
                'word_count' => 42,
                'character_count' => [
                    'length' => 11,
                    'valid' => true,
                    'remaining_characters' => 89,
                ],
            ]);
        $ref->getProperty('response')->setValue($controller, $response);

        $controller->rawWords();
    }

    // ── registerValidators ───────────────────────────────────────────────────

    /**
     * Build a ValidatorTestableCountWordController with the minimal reflection
     * injections needed to run registerValidators() safely:
     * - request stub (param() returns from $params map)
     * - database stub (so getRequest() inside LoginValidator constructor works)
     * - userIsLogged = true (so LoginValidator passes)
     *
     * @param array<string, string|null> $params
     */
    private function buildValidatorController(array $params): ValidatorTestableCountWordController
    {
        $controller = new ValidatorTestableCountWordController();
        $ref = new ReflectionClass(CountWordController::class);

        $request = $this->createStub(Request::class);
        $request->method('param')->willReturnCallback(
            static fn(string $key, mixed $default = null) => $params[$key] ?? $default
        );
        $ref->getProperty('request')->setValue($controller, $request);
        $ref->getProperty('database')->setValue($controller, $this->createStub(IDatabase::class));
        $ref->getProperty('userIsLogged')->setValue($controller, true);

        return $controller;
    }

    #[Test]
    public function registerValidators_sets_default_language_when_language_param_is_absent(): void
    {
        $controller = $this->buildValidatorController(['text' => 'hello']);
        $ref = new ReflectionClass(CountWordController::class);

        $method = $ref->getMethod('registerValidators');
        $method->setAccessible(true);
        $method->invoke($controller);

        self::assertSame('en-US', $ref->getProperty('language')->getValue($controller));
    }

    #[Test]
    public function registerValidators_uses_provided_language_param(): void
    {
        $controller = $this->buildValidatorController(['text' => 'ciao', 'language' => 'it-IT']);
        $ref = new ReflectionClass(CountWordController::class);

        $method = $ref->getMethod('registerValidators');
        $method->setAccessible(true);
        $method->invoke($controller);

        self::assertSame('it-IT', $ref->getProperty('language')->getValue($controller));
    }

    #[Test]
    public function registerValidators_throws_ValidationError_when_text_is_null(): void
    {
        $this->expectException(ValidationError::class);

        $controller = $this->buildValidatorController(['text' => null]);
        $ref = new ReflectionClass(CountWordController::class);

        $method = $ref->getMethod('registerValidators');
        $method->setAccessible(true);
        $method->invoke($controller);
    }

    #[Test]
    public function registerValidators_throws_ValidationError_when_text_is_empty_string(): void
    {
        $this->expectException(ValidationError::class);

        $controller = $this->buildValidatorController(['text' => '']);
        $ref = new ReflectionClass(CountWordController::class);

        $method = $ref->getMethod('registerValidators');
        $method->setAccessible(true);
        $method->invoke($controller);
    }

    #[Test]
    public function registerValidators_throws_ValidationError_for_invalid_language(): void
    {
        $this->expectException(ValidationError::class);

        $controller = $this->buildValidatorController(['text' => 'hello', 'language' => 'xx-NOTREAL']);
        $ref = new ReflectionClass(CountWordController::class);

        $method = $ref->getMethod('registerValidators');
        $method->setAccessible(true);
        $method->invoke($controller);
    }

    #[Test]
    public function registerValidators_appends_LoginValidator_for_valid_inputs(): void
    {
        $controller = $this->buildValidatorController(['text' => 'hello', 'language' => 'en-US']);
        $ref = new ReflectionClass(CountWordController::class);

        $method = $ref->getMethod('registerValidators');
        $method->setAccessible(true);
        $method->invoke($controller);

        $validators = $ref->getProperty('validators')->getValue($controller);
        self::assertCount(1, $validators);
        self::assertInstanceOf(LoginValidator::class, $validators[0]);
    }
}
