<?php

namespace Matecat\Core\Controllers\Authentication;

use Controller\Abstracts\Authentication\AuthenticationHelper;
use Controller\Abstracts\Authentication\AuthenticationTrait;
use Matecat\TestHelpers\AbstractTest;
use Model\ApiKeys\ApiKeyStruct;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use Utils\ActiveMQ\AMQHandler;

/**
 * Concrete class using AuthenticationTrait without overriding its methods.
 */
class TraitTestSubject
{
    use AuthenticationTrait;

    protected ?ApiKeyStruct $api_record = null;
    private ?AuthenticationHelper $stubHelper = null;

    public function __construct()
    {
        $this->userIsLogged = false;
        $this->user         = new UserStruct();
        $this->api_key      = null;
        $this->api_secret   = null;
    }

    public function callIdentifyUser(?AuthenticationHelper $authHelper = null): void
    {
        $this->stubHelper = $authHelper;
        $this->identifyUser(false);
    }

    public function callLogout(?AuthenticationHelper $authHelper = null): void
    {
        $this->stubHelper = $authHelper;
        $this->logout();
    }

    protected function buildAuthHelper(array &$session, ?string $api_key = null, ?string $api_secret = null): AuthenticationHelper
    {
        return $this->stubHelper ?? AuthenticationHelper::fromRequest($session, $this->getDatabase(), $api_key, $api_secret);
    }

    public function callBroadcastLogout(?AMQHandler $amqHandler = null): void
    {
        $this->broadcastLogout($amqHandler);
    }

    public function getDatabase(): IDatabase
    {
        return Database::obtain();
    }

    public static function sessionStart(): void
    {
        // no-op in tests
    }
}

class AuthenticationTraitTest extends AbstractTest
{
    // ─── isLoggedIn / getUser / getApiRecord ────────────────────────────

    #[Test]
    public function isLoggedInReturnsFalseByDefault(): void
    {
        $this->assertFalse((new TraitTestSubject())->isLoggedIn());
    }

    #[Test]
    public function getUserReturnsUserStruct(): void
    {
        $this->assertInstanceOf(UserStruct::class, (new TraitTestSubject())->getUser());
    }

    #[Test]
    public function getApiRecordReturnsNullByDefault(): void
    {
        $this->assertNull((new TraitTestSubject())->getApiRecord());
    }

    // ─── identifyUser ────────────────────────────────────────────────────

    #[Test]
    public function identifyUserSetsUserAndLoggedFromInjectedHelper(): void
    {
        $user = new UserStruct();
        $user->uid   = 42;
        $user->email = 'test@example.com';

        $helper = $this->createStub(AuthenticationHelper::class);
        $helper->method('getUser')->willReturn($user);
        $helper->method('isLogged')->willReturn(true);
        $helper->method('getApiRecord')->willReturn(null);

        $subject = new TraitTestSubject();
        $subject->callIdentifyUser($helper);

        $this->assertTrue($subject->isLoggedIn());
        $this->assertSame(42, $subject->getUser()->uid);
    }

    #[Test]
    public function identifyUserSetsApiRecordFromInjectedHelper(): void
    {
        $apiRecord = new ApiKeyStruct([
            'api_key' => 'k1', 'api_secret' => 's1', 'uid' => 1,
            'enabled' => true, 'create_date' => '2024-01-01', 'last_update' => '2024-01-01',
        ]);

        $helper = $this->createStub(AuthenticationHelper::class);
        $helper->method('getUser')->willReturn(new UserStruct());
        $helper->method('isLogged')->willReturn(false);
        $helper->method('getApiRecord')->willReturn($apiRecord);

        $subject = new TraitTestSubject();
        $subject->callIdentifyUser($helper);

        $this->assertSame($apiRecord, $subject->getApiRecord());
    }

    // ─── logout ──────────────────────────────────────────────────────────

    #[Test]
    public function logoutCallsDestroyAuthenticationOnInjectedHelper(): void
    {
        $_SESSION ??= [];

        $helper = $this->createMock(AuthenticationHelper::class);
        $helper->expects($this->once())->method('destroyAuthentication');

        (new TraitTestSubject())->callLogout($helper);
    }

    // ─── broadcastLogout ─────────────────────────────────────────────────

    #[Test]
    public function broadcastLogoutPublishesToQueueWithInjectedHandler(): void
    {
        $_SESSION ??= [];

        $user      = new UserStruct();
        $user->uid = 99;

        $identifyHelper = $this->createStub(AuthenticationHelper::class);
        $identifyHelper->method('getUser')->willReturn($user);
        $identifyHelper->method('isLogged')->willReturn(true);
        $identifyHelper->method('getApiRecord')->willReturn(null);

        $subject = new TraitTestSubject();
        $subject->callIdentifyUser($identifyHelper);

        $amqHandler = $this->createMock(AMQHandler::class);
        $amqHandler->expects($this->once())->method('publishToNodeJsClients');

        $subject->callBroadcastLogout($amqHandler);
    }
}
