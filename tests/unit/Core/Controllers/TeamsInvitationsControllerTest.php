<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\TeamsInvitationsController;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Exception as PHPUnitException;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use TypeError;
use Utils\Registry\AppConfig;
use Utils\Tools\SimpleJWT;
use Utils\Url\CanonicalRoutes;

/**
 * {@see TeamsInvitationsController::collectBackInvitation()} only builds an
 * InvitedUser (ctor requires TeamDao/UserDao but they are never queried on
 * this path) and calls prepareUserInvitedSignUpRedirect(), which is pure
 * session/FlashMessage bookkeeping. No DAO query runs, so no reserved-id
 * seeding is needed; database is still injected as a real IDatabase
 * (obtainTestDatabase()) per harness convention, never a mock.
 */
class TestableTeamsInvitationsController extends TeamsInvitationsController
{
    public function __construct()
    {
    }
}

class TeamsInvitationsControllerTest extends AbstractTest
{

    /** @var ReflectionClass<TeamsInvitationsController> */
    private ReflectionClass $reflector;
    private TestableTeamsInvitationsController $controller;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws MockObjectException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];

        $this->controller = new TestableTeamsInvitationsController();
        $this->reflector  = new ReflectionClass(TeamsInvitationsController::class);

        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('response', $this->responseMock);
        $this->setProp('database', obtainTestDatabase());
    }

    /**
     * @param array<string, string> $params
     *
     * @throws ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $serverParams = ['REQUEST_URI' => '/api/app/team/invitation', 'REQUEST_METHOD' => 'GET'];
        $this->setProp('request', new Request($params, [], [], $serverParams));
    }

    /**
     * @throws ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $prop = $this->reflector->getProperty($name);
        $prop->setValue($this->controller, $value);
    }

    /**
     * @throws TypeError
     * @throws \UnexpectedValueException
     */
    private function makeToken(string $email): string
    {
        $jwt = new SimpleJWT(['email' => $email], 'simple.jwt.claims', AppConfig::$AUTHSECRET);

        return (string) $jwt;
    }

    // ─── collectBackInvitation() ───

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws PHPUnitException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function collectBackInvitation_stores_session_flash_and_redirects(): void
    {
        $token = $this->makeToken('invited@example.org');
        $this->setRequestParams(['jwt' => $token]);

        $this->responseMock->expects($this->once())
            ->method('redirect')
            ->with(CanonicalRoutes::appRoot())
            ->willReturnSelf();

        $this->controller->collectBackInvitation();

        $this->assertSame(['email' => 'invited@example.org'], $_SESSION['invited_to_team']);
        $this->assertSame(
            [
                'key'   => 'popup',
                'value' => 'signup',
            ],
            $_SESSION['flashMessages']['service'][0]
        );
        $this->assertSame(
            [
                'key'   => 'signup_email',
                'value' => 'invited@example.org',
            ],
            $_SESSION['flashMessages']['service'][1]
        );
    }

    /**
     * A missing jwt param means Request::param('jwt') returns null; the
     * controller passes it straight into InvitedUser's non-nullable `string
     * $jwt = ''` parameter with an explicit null argument, which PHP does not
     * coerce -> TypeError, before prepareUserInvitedSignUpRedirect() or the
     * redirect are ever reached.
     *
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws PHPUnitException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function collectBackInvitation_with_missing_jwt_throws_type_error(): void
    {
        $this->setRequestParams([]);

        $this->responseMock->expects($this->never())->method('redirect');

        $this->expectException(TypeError::class);

        $this->controller->collectBackInvitation();
    }
}
