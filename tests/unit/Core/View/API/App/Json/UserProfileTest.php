<?php

namespace Matecat\Core\View\API\App\Json;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use Predis\Client;
use Predis\ClientInterface;
use View\API\App\Json\UserProfile;

#[CoversClass(UserProfile::class)]
class UserProfileTest extends AbstractTest
{
    private function makeUser(int $uid = 1): UserStruct
    {
        $user             = new UserStruct();
        $user->uid        = $uid;
        $user->first_name = 'Test';
        $user->last_name  = 'User';
        $user->email      = 'test@example.com';
        $user->pass       = null;

        return $user;
    }

    /**
     * The client is threaded straight through to Team, which only reaches for it once a team is
     * rendered — every case here renders none, so a bare stub is enough.
     */
    private function redis(): ClientInterface
    {
        return $this->createStub(Client::class);
    }

    public function testRenderItemReturnsExpectedKeys(): void
    {
        $view   = new UserProfile();
        $result = $view->renderItem($this->makeUser(), [], new UserDao(obtainTestDatabase()), $this->redis());

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('connected_services', $result);
        $this->assertArrayHasKey('teams', $result);
        $this->assertArrayHasKey('metadata', $result);
    }

    public function testRenderItemUserSection(): void
    {
        $user   = $this->makeUser(42);
        $view   = new UserProfile();
        $result = $view->renderItem($user, [], new UserDao(obtainTestDatabase()), $this->redis());

        $this->assertSame(42, $result['user']['uid']);
        $this->assertSame('Test', $result['user']['first_name']);
        $this->assertSame('test@example.com', $result['user']['email']);
    }

    public function testRenderItemEmptyServicesReturnsEmptyArray(): void
    {
        $view   = new UserProfile();
        $result = $view->renderItem($this->makeUser(), [], new UserDao(obtainTestDatabase()), $this->redis());

        $this->assertSame([], $result['connected_services']);
    }

    public function testRenderItemEmptyTeamsReturnsEmptyArray(): void
    {
        $view   = new UserProfile();
        $result = $view->renderItem($this->makeUser(), [], new UserDao(obtainTestDatabase()), $this->redis());

        $this->assertSame([], $result['teams']);
    }

    public function testRenderItemMetadataNullWhenEmpty(): void
    {
        $view   = new UserProfile();
        $result = $view->renderItem($this->makeUser(), [], new UserDao(obtainTestDatabase()), $this->redis());

        $this->assertNull($result['metadata']);
    }

    public function testRenderItemMetadataReturnedWhenPresent(): void
    {
        $view     = new UserProfile();
        $metadata = ['key' => 'value', 'foo' => 'bar'];
        $result = $view->renderItem($this->makeUser(), [], new UserDao(obtainTestDatabase()), $this->redis(), null, $metadata);

        $this->assertSame($metadata, $result['metadata']);
    }
}
