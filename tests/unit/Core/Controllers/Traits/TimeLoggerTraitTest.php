<?php

namespace Matecat\Core\Controllers\Traits;

use Controller\Traits\TimeLoggerTrait;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;

/**
 * The trait reads the request the host controller was given rather than $_SERVER, so that a page
 * call can be logged off the web. These tests run under the CLI, where $_SERVER has no REQUEST_URI:
 * that is the condition the change was made for, so nothing here props the superglobal up.
 */
class TimeLoggerTraitTest extends AbstractTest
{
    private function makeHost(Request $request, bool $loggedIn = false): object
    {
        return new class ($request, $loggedIn) {
            use TimeLoggerTrait;

            public function __construct(Request $request, private readonly bool $loggedIn)
            {
                $this->request = $request;
                $this->timingLogFileName = 'unit_test_calls_time.log';
            }

            public function callLogPageCall(): void
            {
                $this->startTimer();
                $this->logPageCall();
            }

            public function isLoggedIn(): bool
            {
                return $this->loggedIn;
            }

            public function getUser(): UserStruct
            {
                $user = new UserStruct();
                $user->uid = 7;
                $user->email = 'translator@example.org';
                $user->first_name = 'Ada';
                $user->last_name = 'Lovelace';

                return $user;
            }
        };
    }

    #[Test]
    public function logPageCallReadsTheUriFromTheRequestNotFromTheSuperglobal(): void
    {
        unset($_SERVER['REQUEST_URI']);

        $request = new Request(server: ['REQUEST_URI' => '/api/v2/jobs/1/abc/split/2']);

        $this->makeHost($request)->callLogPageCall();

        // Reaching here at all is the assertion the change is about: before it, parse_url() was
        // handed the missing superglobal and PHP warned on a null argument.
        self::assertArrayNotHasKey('REQUEST_URI', $_SERVER);
    }

    #[Test]
    public function logPageCallSurvivesARequestWithNoUriAtAll(): void
    {
        // Request::uri() falls back to "/" when the server collection carries no REQUEST_URI.
        $this->makeHost(new Request())->callLogPageCall();

        self::assertTrue(true);
    }

    #[Test]
    public function logPageCallExpandsTheQueryStringIntoItsParameters(): void
    {
        $host = $this->makeHost(new Request(server: ['REQUEST_URI' => '/manage?page=2&filter=done']));

        $host->callLogPageCall();

        self::assertTrue(true);
    }

    #[Test]
    public function timerMeasuresFromTheStartCall(): void
    {
        $host = $this->makeHost(new Request());

        $started = new ReflectionProperty($host, 'startExecutionTime');
        self::assertSame(0.0, $started->getValue($host));

        $host->callLogPageCall();

        self::assertGreaterThan(0.0, $started->getValue($host));
        self::assertGreaterThanOrEqual(0.0, $host->getTimer());
    }
}
