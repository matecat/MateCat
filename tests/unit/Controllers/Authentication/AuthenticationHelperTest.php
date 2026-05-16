<?php

namespace unit\Controllers\Authentication;

use Controller\Abstracts\Authentication\AuthenticationHelper;
use Controller\Abstracts\Authentication\CookieManager;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

#[CoversClass(AuthenticationHelper::class)]
#[CoversClass(CookieManager::class)]
class AuthenticationHelperTest extends TestCase
{
    #[Test]
    public function loggedPropertyIsBoolNotTrue(): void
    {
        $session = [];
        $helper = $this->createHelper($session);
        $prop = new ReflectionProperty(AuthenticationHelper::class, 'logged');

        $this->assertIsBool($prop->getValue($helper));
    }

    #[Test]
    public function userPropertyIsAlwaysUserStruct(): void
    {
        $session = [];
        $helper = $this->createHelper($session);
        $prop = new ReflectionProperty(AuthenticationHelper::class, 'user');

        $this->assertInstanceOf(UserStruct::class, $prop->getValue($helper));
    }

    #[Test]
    public function validKeysReturnsFalseWhenBothNull(): void
    {
        $session = [];
        $helper = $this->createHelper($session);
        $method = new ReflectionMethod($helper, 'validKeys');

        // Both null: early return false without DB access
        $this->assertFalse($method->invoke($helper, null, null));
    }

    #[Test]
    public function validKeysReturnsFalseWhenKeyIsEmptyString(): void
    {
        $session = [];
        $helper = $this->createHelper($session);
        $method = new ReflectionMethod($helper, 'validKeys');

        // Empty strings are falsy: early return false without DB access
        $this->assertFalse($method->invoke($helper, '', ''));
    }

    #[Test]
    public function getUserReturnsUserStruct(): void
    {
        $session = [];
        $helper = $this->createHelper($session);

        $this->assertInstanceOf(UserStruct::class, $helper->getUser());
    }

    #[Test]
    public function isLoggedReturnsBoolWhenNoAuth(): void
    {
        $session = [];
        $helper = $this->createHelper($session);

        $this->assertFalse($helper->isLogged());
    }

    #[Test]
    public function getApiRecordReturnsNullByDefault(): void
    {
        $session = [];
        $helper = $this->createHelper($session);

        $this->assertNull($helper->getApiRecord());
    }

    #[Test]
    public function sessionPropertyIsArrayReference(): void
    {
        $session = ['test_key' => 'test_value'];
        $helper = $this->createHelper($session);
        $prop = new ReflectionProperty(AuthenticationHelper::class, 'session');
        $value = $prop->getValue($helper);

        $this->assertIsArray($value);
        $this->assertSame('test_value', $value['test_key']);
    }

    #[Test]
    public function getUserProfileReturnsArray(): void
    {
        $method = new ReflectionMethod(AuthenticationHelper::class, 'getUserProfile');

        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'test@test.com';
        $user->first_name = 'Test';
        $user->last_name = 'User';

        $result = $method->invoke(null, $user);

        $this->assertIsArray($result);
    }

    private function createHelper(array &$session): AuthenticationHelper
    {
        return TestableAuthenticationHelper::create($session);
    }
}

class TestableAuthenticationHelper extends AuthenticationHelper
{
    public static function create(array &$session): self
    {
        $instance = new self($session);

        return $instance;
    }
}
