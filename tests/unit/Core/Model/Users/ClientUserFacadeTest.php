<?php


namespace Matecat\Core\Model\Users;

use Matecat\TestHelpers\AbstractTest;
use Model\Users\ClientUserFacade;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;

class ClientUserFacadeTest extends AbstractTest
{
    #[Test]
    public function constructorCopiesMatchingProperties(): void
    {
        $user = new UserStruct();
        $user->uid = 42;
        $user->email = 'test@example.com';
        $user->first_name = 'John';
        $user->last_name = 'Doe';
        $user->salt = 'should-not-copy';

        $facade = new ClientUserFacade($user);

        $this->assertSame(42, $facade->uid);
        $this->assertSame('test@example.com', $facade->email);
        $this->assertSame('John', $facade->first_name);
        $this->assertSame('Doe', $facade->last_name);
        $this->assertFalse(property_exists($facade, 'salt'));
    }

    #[Test]
    public function toStringReturnsJson(): void
    {
        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'a@b.com';
        $user->first_name = 'A';
        $user->last_name = 'B';

        $facade = new ClientUserFacade($user);
        $json = (string)$facade;

        $decoded = json_decode($json, true);
        $this->assertSame(1, $decoded['uid']);
        $this->assertSame('a@b.com', $decoded['email']);
    }
}
