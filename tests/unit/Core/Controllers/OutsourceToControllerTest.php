<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\OutsourceToController;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionException;
use Utils\Logger\MatecatLogger;
use Utils\OutsourceTo\Translated;

/**
 * Testable subclass: empty constructor bypasses Klein DI wiring so properties
 * can be injected via reflection, and the external quote-service seam
 * (getOutsourceService) is overridden to avoid any live outbound HTTP call.
 */
class TestableOutsourceToController extends OutsourceToController
{
    public ?Translated $outsourceServiceStub = null;

    public function __construct()
    {
    }

    protected function getOutsourceService(): Translated
    {
        /** @var Translated $stub */
        $stub = $this->outsourceServiceStub;

        return $stub;
    }
}

/**
 * OutsourceToControllerTest (Wave 2, Medium slot).
 *
 * No DAO usage in this controller: no DB seeding is performed (DB-ID block
 * 9_982_000..9_982_999 reserved but unused — "none" seeded).
 *
 * outsource() builds `new Translated()` (lib/Utils/OutsourceTo/Translated.php)
 * and calls performQuote(), which reaches an external quote vendor over HTTP
 * with no test-env guard. To avoid any live network call, the controller was
 * given a protected `getOutsourceService(): Translated` seam (mirroring the
 * campaign's sanctioned external-service seam pattern); the Testable subclass
 * overrides it to return a PHPUnit stub configured with canned quote data.
 */
class OutsourceToControllerTest extends AbstractTest
{
    private const int BASE = 9_982_000;

    private ReflectionClass $reflector;
    private TestableOutsourceToController $controller;

    /** @var array<string, mixed> */
    private array $cookieBackup = [];

    /**
     * @throws ReflectionException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cookieBackup = $_COOKIE;
        $_COOKIE = [];

        $this->controller = new TestableOutsourceToController();
        $this->reflector  = new ReflectionClass(OutsourceToController::class);

        $this->setProp('request', new Request());
        $this->setProp('response', new Response());

        $user             = new UserStruct();
        $user->uid        = self::BASE + 1;
        $user->email      = 'ctrltest_' . self::BASE . '@example.org';
        $user->first_name = 'Outsource';
        $user->last_name  = 'Tester';
        $this->setProp('user', $user);
        $this->setProp('userIsLogged', true);

        $dbStub = $this->createStub(\Model\DataAccess\IDatabase::class);
        $this->setProp('logger', $this->createStub(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($dbStub));
        $this->setProp('database', obtainTestDatabase());
    }

    protected function tearDown(): void
    {
        $_COOKIE = $this->cookieBackup;

        parent::tearDown();
    }

    private function setProp(string $name, mixed $value): void
    {
        $p = $this->reflector->getProperty($name);
        $p->setValue($this->controller, $value);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function setRequestParams(array $params): void
    {
        $serverParams = ['REQUEST_URI' => '/api/app/outsource', 'REQUEST_METHOD' => 'POST'];
        $this->setProp('request', new Request($params, [], [], $serverParams));
    }

    /**
     * @return array<string, mixed>
     * @throws ReflectionException
     */
    private function callValidateTheRequest(): array
    {
        $m = $this->reflector->getMethod('validateTheRequest');
        $m->setAccessible(true);

        /** @var array<string, mixed> $result */
        $result = $m->invoke($this->controller);

        return $result;
    }

    private function jobsParam(): array
    {
        return [
            0 => [
                'id'        => '5901',
                'jpassword' => '6decb661a182',
            ],
        ];
    }

    // ─── validateTheRequest: happy path ───

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validate_the_request_returns_expected_array_on_happy_path(): void
    {
        $this->setRequestParams([
            'pid'            => '5901',
            'ppassword'      => '6decb661a182',
            'currency'       => 'EUR',
            'timezone'       => 'Europe/Rome',
            'fixedDelivery'  => '1000',
            'typeOfService'  => 'premium',
            'jobs'           => $this->jobsParam(),
        ]);

        $result = $this->callValidateTheRequest();

        self::assertSame('5901', $result['pid']);
        self::assertSame('6decb661a182', $result['ppassword']);
        self::assertSame('EUR', $result['currency']);
        self::assertSame('Europe/Rome', $result['timezone']);
        self::assertSame('premium', $result['typeOfService']);
        self::assertIsArray($result['jobList']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validate_the_request_defaults_type_of_service_to_professional_when_invalid(): void
    {
        $this->setRequestParams([
            'pid'           => '5901',
            'ppassword'     => '6decb661a182',
            'typeOfService' => 'bogus',
            'jobs'          => $this->jobsParam(),
        ]);

        $result = $this->callValidateTheRequest();

        self::assertSame('professional', $result['typeOfService']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validate_the_request_falls_back_to_currency_cookie_when_param_empty(): void
    {
        $_COOKIE['matecat_currency'] = 'USD';

        $this->setRequestParams([
            'pid'       => '5901',
            'ppassword' => '6decb661a182',
            'jobs'      => $this->jobsParam(),
        ]);

        $result = $this->callValidateTheRequest();

        self::assertSame('USD', $result['currency']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validate_the_request_falls_back_to_timezone_cookie_when_param_empty(): void
    {
        $_COOKIE['matecat_timezone'] = 'Europe/Rome';

        $this->setRequestParams([
            'pid'       => '5901',
            'ppassword' => '6decb661a182',
            'jobs'      => $this->jobsParam(),
        ]);

        $result = $this->callValidateTheRequest();

        self::assertSame('Europe/Rome', $result['timezone']);
    }

    // ─── validateTheRequest: throw branches ───

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validate_the_request_throws_when_pid_is_empty(): void
    {
        $this->setRequestParams([
            'ppassword' => '6decb661a182',
            'jobs'      => $this->jobsParam(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No id project provided');

        $this->callValidateTheRequest();
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validate_the_request_throws_when_ppassword_is_empty(): void
    {
        $this->setRequestParams([
            'pid'  => '5901',
            'jobs' => $this->jobsParam(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No project Password Provided');

        $this->callValidateTheRequest();
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validate_the_request_throws_when_job_list_is_empty(): void
    {
        $this->setRequestParams([
            'pid'       => '5901',
            'ppassword' => '6decb661a182',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No job list Provided');

        $this->callValidateTheRequest();
    }

    // ─── outsource(): happy path via stubbed external service ───

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function outsource_returns_json_with_quotes_and_return_urls(): void
    {
        $this->setRequestParams([
            'pid'           => '5901',
            'ppassword'     => '6decb661a182',
            'currency'      => 'EUR',
            'timezone'      => 'Europe/Rome',
            'fixedDelivery' => '0',
            'typeOfService' => 'premium',
            'jobs'          => $this->jobsParam(),
        ]);

        $stub = $this->createStub(Translated::class);
        $stub->method('setPid')->willReturnSelf();
        $stub->method('setPpassword')->willReturnSelf();
        $stub->method('setCurrency')->willReturnSelf();
        $stub->method('setTimezone')->willReturnSelf();
        $stub->method('setJobList')->willReturnSelf();
        $stub->method('setFixedDelivery')->willReturnSelf();
        $stub->method('setTypeOfService')->willReturnSelf();
        $stub->method('setUser')->willReturnSelf();
        $stub->method('setFeatures')->willReturnSelf();
        $stub->method('getQuotesResult')->willReturn([
            '5901-6decb661a182' => [
                'id'    => '5901-6decb661a182',
                'price' => '12.00',
            ],
        ]);
        $stub->method('getOutsourceLoginUrlOk')->willReturn('https://example.org/success');
        $stub->method('getOutsourceLoginUrlKo')->willReturn('https://example.org/failure');
        $stub->method('getOutsourceConfirmUrl')->willReturn(['https://example.org/confirm/5901/6decb661a182']);

        $this->controller->outsourceServiceStub = $stub;

        // outsource() calls Response::json(), which send()s the body to stdout and pollutes
        // the test-runner output. Swallow that echo; the body is still recorded on the
        // Response object, so the assertions below are unaffected.
        ob_start();
        try {
            $this->controller->outsource();
        } finally {
            ob_end_clean();
        }

        $response = $this->reflector->getProperty('response')->getValue($this->controller);
        self::assertInstanceOf(Response::class, $response);

        $body = json_decode($response->body(), true);

        self::assertSame(1, $body['code']);
        self::assertSame([], $body['errors']);
        self::assertSame(
            [['id' => '5901-6decb661a182', 'price' => '12.00']],
            $body['data']
        );
        self::assertSame('https://example.org/success', $body['return_url']['url_ok']);
        self::assertSame('https://example.org/failure', $body['return_url']['url_ko']);
        self::assertSame(
            ['https://example.org/confirm/5901/6decb661a182'],
            $body['return_url']['confirm_urls']
        );
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function outsource_propagates_validation_exception_before_touching_outsource_service(): void
    {
        $this->setRequestParams([
            'ppassword' => '6decb661a182',
            'jobs'      => $this->jobsParam(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No id project provided');

        $this->controller->outsource();
    }
}
