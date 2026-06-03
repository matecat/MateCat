<?php

namespace Matecat\Core\View\API\V2\Json;

use Matecat\TestHelpers\AbstractTest;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use View\API\V2\Json\User;

#[CoversClass(User::class)]
class UserTest extends AbstractTest
{
    private function makeUser(int $uid = 1, ?string $pass = null): UserStruct
    {
        $user             = new UserStruct();
        $user->uid        = $uid;
        $user->first_name = 'Jane';
        $user->last_name  = 'Doe';
        $user->email      = 'jane@example.com';
        $user->pass       = $pass;

        return $user;
    }

    public function testRenderItemReturnsExpectedKeys(): void
    {
        $result = User::renderItem($this->makeUser());

        $this->assertArrayHasKey('uid', $result);
        $this->assertArrayHasKey('first_name', $result);
        $this->assertArrayHasKey('last_name', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('has_password', $result);
    }

    public function testRenderItemHasPasswordFalseWhenNull(): void
    {
        $result = User::renderItem($this->makeUser(1, null));

        $this->assertFalse($result['has_password']);
    }

    public function testRenderItemHasPasswordTrueWhenSet(): void
    {
        $result = User::renderItem($this->makeUser(1, 'hashed'));

        $this->assertTrue($result['has_password']);
    }

    public function testRenderItemCastsUidToInt(): void
    {
        $result = User::renderItem($this->makeUser(42));

        $this->assertSame(42, $result['uid']);
    }

    public function testRenderItemPublicReturnsExpectedKeys(): void
    {
        $result = User::renderItemPublic($this->makeUser());

        $this->assertArrayHasKey('uid', $result);
        $this->assertArrayHasKey('first_name', $result);
        $this->assertArrayHasKey('last_name', $result);
        $this->assertArrayNotHasKey('email', $result);
        $this->assertArrayNotHasKey('has_password', $result);
    }

    public function testRenderItemPublicValues(): void
    {
        $result = User::renderItemPublic($this->makeUser(7));

        $this->assertSame(7, $result['uid']);
        $this->assertSame('Jane', $result['first_name']);
        $this->assertSame('Doe', $result['last_name']);
    }
}
