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

    /**
     * The template used to echo the team name unescaped and relied on the name having been
     * entity-encoded when it was stored. Escaping now happens where the value is written,
     * so the email is safe on its own terms rather than on an assumption about the column.
     */
    #[Test]
    public function theTeamNameIsEscapedInTheRenderedBody(): void
    {
        $team = $this->makeTeam();
        $team->name = '<img src=x onerror=alert(1)>';

        $body = $this->renderBody($team);

        $this->assertStringNotContainsString('<img', $body);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $body);
    }

    /**
     * A name that was already entity-encoded on the way into the database must not be
     * encoded a second time, or the reader sees "&amp;lt;" instead of "<".
     */
    #[Test]
    public function anAlreadyEncodedTeamNameIsNotEncodedTwice(): void
    {
        $team = $this->makeTeam();
        $team->name = '&lt;Encoded&gt; &amp; Co';

        $body = $this->renderBody($team);

        $this->assertStringContainsString('&lt;Encoded&gt; &amp; Co', $body);
        $this->assertStringNotContainsString('&amp;lt;', $body);
    }

    #[Test]
    public function anOrdinaryTeamNameIsRenderedAsIs(): void
    {
        $team = $this->makeTeam();
        $team->name = 'Marketing Team';

        $this->assertStringContainsString('Marketing Team', $this->renderBody($team));
    }

    /**
     * Renders the invitation body through the production template path.
     *
     * @throws \ReflectionException
     */
    private function renderBody(TeamStruct $team): string
    {
        $email = new InvitedToTeamEmail($this->makeUser(), 'invited@example.com', $team);
        $method = new ReflectionMethod(InvitedToTeamEmail::class, '_buildMessageContent');

        return (string)$method->invoke($email);
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
