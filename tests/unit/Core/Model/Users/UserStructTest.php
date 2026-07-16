<?php


namespace Matecat\Core\Model\Users;

use Matecat\TestHelpers\AbstractTest;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class UserStructTest extends AbstractTest
{
    #[Test]
    public function isLoggedReturnsTrueWhenAllFieldsSet(): void
    {
        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'a@b.com';
        $user->first_name = 'A';
        $user->last_name = 'B';

        $this->assertTrue($user->isLogged());
        $this->assertFalse($user->isAnonymous());
    }

    #[Test]
    public function isLoggedReturnsFalseWhenMissingFields(): void
    {
        $user = new UserStruct();

        $this->assertFalse($user->isLogged());
        $this->assertTrue($user->isAnonymous());
    }

    #[Test]
    public function fullNameCombinesFirstAndLast(): void
    {
        $user = new UserStruct();
        $user->first_name = 'John';
        $user->last_name = 'Doe';

        $this->assertSame('John Doe', $user->fullName());
    }

    #[Test]
    public function shortNameReturnsInitials(): void
    {
        $user = new UserStruct();
        $user->first_name = 'John';
        $user->last_name = 'Doe';

        $this->assertSame('JD', $user->shortName());
    }

    #[Test]
    public function shortNameHandlesNullNames(): void
    {
        $user = new UserStruct();

        $this->assertSame('', $user->shortName());
    }

    #[Test]
    public function gettersReturnProperties(): void
    {
        $user = new UserStruct();
        $user->uid = 42;
        $user->email = 'test@example.com';
        $user->first_name = 'A';
        $user->last_name = 'B';

        $this->assertSame(42, $user->getUid());
        $this->assertSame('test@example.com', $user->getEmail());
        $this->assertSame('A', $user->getFirstName());
        $this->assertSame('B', $user->getLastName());
    }

    #[Test]
    public function getStructReturnsNewInstance(): void
    {
        $struct = UserStruct::getStruct();

        $this->assertInstanceOf(UserStruct::class, $struct);
        $this->assertNull($struct->uid);
    }

    #[Test]
    public function everSignedInReturnsFalseForFreshUser(): void
    {
        $user = new UserStruct();

        $this->assertFalse($user->everSignedIn());
    }

    #[Test]
    public function everSignedInReturnsTrueWithEmailConfirmed(): void
    {
        $user = new UserStruct();
        $user->email_confirmed_at = '2026-01-01 00:00:00';

        $this->assertTrue($user->everSignedIn());
    }

    #[Test]
    public function clearAuthTokenNullifiesFields(): void
    {
        $user = new UserStruct();
        $user->confirmation_token = 'abc';
        $user->confirmation_token_created_at = '2026-01-01';

        $user->clearAuthToken();

        $this->assertNull($user->confirmation_token);
        $this->assertNull($user->confirmation_token_created_at);
    }

    #[Test]
    public function initAuthTokenSetsFields(): void
    {
        $user = new UserStruct();

        $user->initAuthToken();

        $this->assertNotNull($user->confirmation_token);
        $this->assertNotNull($user->confirmation_token_created_at);
        $this->assertSame(50, strlen($user->confirmation_token));
    }

    #[Test]
    public function passwordMatchThrowsWhenSaltNull(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User salt must be set');

        $user = new UserStruct();
        $user->passwordMatch('test');
    }

    #[Test]
    public function passwordMatchThrowsWhenPassNull(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User password must be set');

        $user = new UserStruct();
        $user->salt = 'somesalt';
        $user->passwordMatch('test');
    }

    #[Test]
    public function getDecryptedOauthAccessTokenReturnsNullWhenNoToken(): void
    {
        $user = new UserStruct();

        $this->assertNull($user->getDecryptedOauthAccessToken());
    }
}
