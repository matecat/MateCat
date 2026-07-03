<?php

namespace Matecat\Core\Utils\Email;

use Matecat\TestHelpers\AbstractTest;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Utils\Email\WelcomeEmail;


class WelcomeEmailTest extends AbstractTest
{
    private function makeUser(): UserStruct
    {
        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'user@example.com';
        $user->first_name = 'John';
        $user->last_name = 'Doe';

        return $user;
    }

    #[Test]
    public function getTemplateVariablesReturnsUserArray(): void
    {
        $email = new WelcomeEmail($this->makeUser());
        $method = new ReflectionMethod(WelcomeEmail::class, '_getTemplateVariables');
        $vars = $method->invoke($email);

        $this->assertArrayHasKey('user', $vars);
        $this->assertIsArray($vars['user']);
    }

    #[Test]
    public function getLayoutVariablesSetsClosingLine(): void
    {
        $email = new WelcomeEmail($this->makeUser());
        $method = new ReflectionMethod(WelcomeEmail::class, '_getLayoutVariables');
        $vars = $method->invoke($email);

        $this->assertArrayHasKey('title', $vars);
        $this->assertArrayHasKey('closingLine', $vars);
        $this->assertSame('Happy translating!', $vars['closingLine']);
    }

    #[Test]
    public function getDefaultMailConfOverridesFrom(): void
    {
        $email = new WelcomeEmail($this->makeUser());
        $ref = new ReflectionMethod(WelcomeEmail::class, '_getDefaultMailConf');
        $conf = $ref->invoke($email);

        $this->assertSame('noreply@matecat.com', $conf['from']);
        $this->assertSame('noreply@matecat.com', $conf['sender']);
        $this->assertSame('noreply@matecat.com', $conf['returnPath']);
    }

    #[Test]
    public function sendCallsDoSend(): void
    {
        $email = $this->getMockBuilder(WelcomeEmail::class)
            ->setConstructorArgs([$this->makeUser()])
            ->onlyMethods(['doSend'])
            ->getMock();

        $email->expects($this->once())
            ->method('doSend')
            ->willReturn(true);

        $email->send();
    }
}
