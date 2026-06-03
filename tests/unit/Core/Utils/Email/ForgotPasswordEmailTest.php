<?php

namespace Matecat\Core\Utils\Email;

use Matecat\TestHelpers\AbstractTest;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Utils\Email\ForgotPasswordEmail;

class ForgotPasswordEmailTest extends AbstractTest
{
    private function makeUser(): UserStruct
    {
        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'user@example.com';
        $user->first_name = 'Jane';
        $user->last_name = 'Doe';
        $user->confirmation_token = 'reset-token-123';

        return $user;
    }

    #[Test]
    public function getTemplateVariablesReturnsExpectedKeys(): void
    {
        $email = new ForgotPasswordEmail($this->makeUser());
        $method = new ReflectionMethod(ForgotPasswordEmail::class, '_getTemplateVariables');
        $vars = $method->invoke($email);

        $this->assertArrayHasKey('user', $vars);
        $this->assertArrayHasKey('password_reset_url', $vars);
        $this->assertIsString($vars['password_reset_url']);
    }

    #[Test]
    public function getTemplateVariablesHandlesNullToken(): void
    {
        $user = $this->makeUser();
        $user->confirmation_token = null;

        $email = new ForgotPasswordEmail($user);
        $method = new ReflectionMethod(ForgotPasswordEmail::class, '_getTemplateVariables');
        $vars = $method->invoke($email);

        $this->assertArrayHasKey('password_reset_url', $vars);
    }

    #[Test]
    public function getLayoutVariablesSetsTitle(): void
    {
        $email = new ForgotPasswordEmail($this->makeUser());
        $method = new ReflectionMethod(ForgotPasswordEmail::class, '_getLayoutVariables');
        $vars = $method->invoke($email);

        $this->assertArrayHasKey('title', $vars);
    }

    #[Test]
    public function sendCallsDoSend(): void
    {
        $email = $this->getMockBuilder(ForgotPasswordEmail::class)
            ->setConstructorArgs([$this->makeUser()])
            ->onlyMethods(['doSend'])
            ->getMock();

        $email->expects($this->once())
            ->method('doSend')
            ->willReturn(true);

        $email->send();
    }
}
