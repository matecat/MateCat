<?php

namespace Matecat\Core\Utils\Email;

use Matecat\TestHelpers\AbstractTest;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Utils\Email\SignupEmail;

class SignupEmailTest extends AbstractTest
{
    private function makeUser(): UserStruct
    {
        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'user@example.com';
        $user->first_name = 'John';
        $user->last_name = 'Doe';
        $user->confirmation_token = 'abc123token';

        return $user;
    }

    #[Test]
    public function getTemplateVariablesReturnsExpectedKeys(): void
    {
        $email = new SignupEmail($this->makeUser());
        $method = new ReflectionMethod(SignupEmail::class, '_getTemplateVariables');
        $vars = $method->invoke($email);

        $this->assertArrayHasKey('user', $vars);
        $this->assertArrayHasKey('activation_url', $vars);
        $this->assertArrayHasKey('signup_url', $vars);
        $this->assertIsString($vars['activation_url']);
    }

    #[Test]
    public function getTemplateVariablesHandlesNullToken(): void
    {
        $user = $this->makeUser();
        $user->confirmation_token = null;

        $email = new SignupEmail($user);
        $method = new ReflectionMethod(SignupEmail::class, '_getTemplateVariables');
        $vars = $method->invoke($email);

        $this->assertArrayHasKey('activation_url', $vars);
    }

    #[Test]
    public function getLayoutVariablesSetsTitle(): void
    {
        $email = new SignupEmail($this->makeUser());
        $method = new ReflectionMethod(SignupEmail::class, '_getLayoutVariables');
        $vars = $method->invoke($email);

        $this->assertArrayHasKey('title', $vars);
    }

    #[Test]
    public function getDefaultMailConfOverridesReturnPath(): void
    {
        $email = new SignupEmail($this->makeUser());
        $method = new ReflectionMethod(SignupEmail::class, '_getDefaultMailConf');
        $conf = $method->invoke($email);

        $this->assertArrayHasKey('returnPath', $conf);
    }

    #[Test]
    public function sendCallsDoSend(): void
    {
        $email = $this->getMockBuilder(SignupEmail::class)
            ->setConstructorArgs([$this->makeUser()])
            ->onlyMethods(['doSend'])
            ->getMock();

        $email->expects($this->once())
            ->method('doSend')
            ->willReturn(true);

        $email->send();
    }
}
