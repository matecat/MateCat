<?php

namespace Matecat\Core\Utils\Email;

use Matecat\TestHelpers\AbstractTest;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Utils\Email\InvitedToTeamEmail;

class InvitedToTeamEmailTest extends AbstractTest
{
    private function makeUser(): UserStruct
    {
        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'sender@example.com';
        $user->first_name = 'Admin';
        $user->last_name = 'User';

        return $user;
    }

    private function makeTeam(): TeamStruct
    {
        $team = new TeamStruct();
        $team->id = 10;
        $team->name = 'Test Team';

        return $team;
    }

    #[Test]
    public function getTemplateVariablesReturnsExpectedKeys(): void
    {
        $email = new InvitedToTeamEmail($this->makeUser(), 'invited@example.com', $this->makeTeam());
        $method = new ReflectionMethod(InvitedToTeamEmail::class, '_getTemplateVariables');
        $vars = $method->invoke($email);

        $this->assertArrayHasKey('sender', $vars);
        $this->assertArrayHasKey('email', $vars);
        $this->assertArrayHasKey('team', $vars);
        $this->assertArrayHasKey('signup_url', $vars);
        $this->assertSame('invited@example.com', $vars['email']);
    }

    #[Test]
    public function sendCallsDoSendOnce(): void
    {
        $email = $this->getMockBuilder(InvitedToTeamEmail::class)
            ->setConstructorArgs([$this->makeUser(), 'invited@example.com', $this->makeTeam()])
            ->onlyMethods(['doSend'])
            ->getMock();

        $email->expects($this->once())
            ->method('doSend')
            ->willReturn(true);

        $email->send();
    }
}
