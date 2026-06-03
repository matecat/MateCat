<?php

namespace Matecat\Core\Structs\TestApiKeyStruct;

use Matecat\TestHelpers\AbstractTest;
use Model\ApiKeys\ApiKeyStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(ApiKeyStruct::class)]
class ApiKeyStructTest extends AbstractTest
{
    #[Test]
    public function constructorSetsPropertiesFromArray(): void
    {
        $params = [
            'uid'         => 42,
            'api_key'     => 'k1',
            'api_secret'  => 's1',
            'enabled'     => true,
            'create_date' => '2024-01-01 00:00:00',
            'last_update' => '2024-01-01 00:00:00',
        ];

        $struct = new ApiKeyStruct($params);

        $this->assertSame(42, $struct->uid);
        $this->assertSame('k1', $struct->api_key);
        $this->assertSame('s1', $struct->api_secret);
        $this->assertTrue($struct->enabled);
    }

    #[Test]
    public function constructorUsesInjectedUserDao(): void
    {
        $user = new UserStruct();
        $user->uid = 42;
        $user->email = 'test@example.com';

        /** @var UserDao&MockObject $userDaoMock */
        $userDaoMock = $this->createMock(UserDao::class);
        $userDaoMock->expects($this->once())
            ->method('getByUid')
            ->with(42)
            ->willReturn($user);

        $struct = new ApiKeyStruct(
            ['uid' => 42, 'api_key' => 'k', 'api_secret' => 's', 'enabled' => true, 'create_date' => '2024-01-01', 'last_update' => '2024-01-01'],
            $userDaoMock
        );

        $result = $struct->getUser();
        $this->assertSame($user, $result);
        $this->assertSame(42, $result->uid);
    }

    #[Test]
    public function getUserSetsCacheTTL(): void
    {
        /** @var UserDao&MockObject $userDaoMock */
        $userDaoMock = $this->createMock(UserDao::class);
        $userDaoMock->expects($this->once())
            ->method('setCacheTTL')
            ->with(3600);
        $userDaoMock->method('getByUid')->willReturn(null);

        $struct = new ApiKeyStruct(
            ['uid' => 1, 'api_key' => 'k', 'api_secret' => 's', 'enabled' => true, 'create_date' => '2024-01-01', 'last_update' => '2024-01-01'],
            $userDaoMock
        );

        $struct->getUser();
    }

    #[Test]
    public function getUserReturnsNullWhenUserNotFound(): void
    {
        /** @var UserDao&MockObject $userDaoMock */
        $userDaoMock = $this->createMock(UserDao::class);
        $userDaoMock->method('getByUid')->with(999)->willReturn(null);

        $struct = new ApiKeyStruct(
            ['uid' => 999, 'api_key' => 'k', 'api_secret' => 's', 'enabled' => true, 'create_date' => '2024-01-01', 'last_update' => '2024-01-01'],
            $userDaoMock
        );

        $this->assertNull($struct->getUser());
    }

    #[Test]
    public function validSecretReturnsTrueForMatch(): void
    {
        $struct = new ApiKeyStruct(['uid' => 1, 'api_key' => 'k', 'api_secret' => 'correct', 'enabled' => true, 'create_date' => '2024-01-01', 'last_update' => '2024-01-01']);

        $this->assertTrue($struct->validSecret('correct'));
    }

    #[Test]
    public function validSecretReturnsFalseForMismatch(): void
    {
        $struct = new ApiKeyStruct(['uid' => 1, 'api_key' => 'k', 'api_secret' => 'correct', 'enabled' => true, 'create_date' => '2024-01-01', 'last_update' => '2024-01-01']);

        $this->assertFalse($struct->validSecret('wrong'));
    }
}
