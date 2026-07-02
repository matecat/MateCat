<?php

namespace Matecat\Core\Utils\Email;

use Matecat\TestHelpers\AbstractTest;
use Model\Teams\MembershipStruct;
use Model\Teams\TeamDao;
use Model\Teams\TeamStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;
use ReflectionProperty;
use Utils\Email\MembershipCreatedEmail;
use Utils\Email\MembershipDeletedEmail;

#[CoversClass(MembershipCreatedEmail::class)]
#[CoversClass(MembershipDeletedEmail::class)]
class MembershipEmailTest extends AbstractTest
{
    private function makeUser(int $uid = 1): UserStruct
    {
        $user = new UserStruct();
        $user->uid = $uid;
        $user->email = 'member@example.com';
        $user->first_name = 'John';
        $user->last_name = 'Doe';

        return $user;
    }

    private function makeSender(): UserStruct
    {
        $sender = new UserStruct();
        $sender->uid = 99;
        $sender->email = 'admin@example.com';
        $sender->first_name = 'Admin';
        $sender->last_name = 'User';

        return $sender;
    }

    private function makeMembershipWithTeam(): MembershipStruct
    {
        $struct = new MembershipStruct();
        $struct->id = 1;
        $struct->id_team = 10;
        $struct->uid = 1;

        $team = new TeamStruct();
        $team->id = 10;
        $team->name = 'Test Team';

        $struct->setUser($this->makeUser());

        $teamProp = new ReflectionProperty(MembershipStruct::class, 'team');
        $teamProp->setValue($struct, $team);

        return $struct;
    }

    private function makeTeam(string $name = 'Test Team'): TeamStruct
    {
        $team = new TeamStruct();
        $team->id = 10;
        $team->name = $name;

        return $team;
    }

    private function makeUserDao(): UserDao
    {
        $stub = $this->createStub(UserDao::class);
        $stub->method('setCacheTTL')->willReturnSelf();
        $stub->method('getByUid')->willReturn($this->makeUser());
        return $stub;
    }

    private function makeTeamDao(): TeamDao
    {
        $stub = $this->createStub(TeamDao::class);
        $stub->method('setCacheTTL')->willReturnSelf();
        $stub->method('fetchById')->willReturn($this->makeTeam());
        return $stub;
    }

    public function testMembershipCreatedEmailConstruction(): void
    {
        $email = new MembershipCreatedEmail(
            $this->makeSender(),
            $this->makeMembershipWithTeam(),
            $this->makeUserDao(),
            $this->makeTeamDao()
        );
        $this->assertInstanceOf(MembershipCreatedEmail::class, $email);
    }

    public function testMembershipCreatedEmailGetLayoutVariables(): void
    {
        $email = new MembershipCreatedEmail(
            $this->makeSender(),
            $this->makeMembershipWithTeam(),
            $this->makeUserDao(),
            $this->makeTeamDao()
        );
        $vars = $email->_getLayoutVariables();

        $this->assertArrayHasKey('title', $vars);
        $this->assertStringContainsString('Test Team', $vars['title']);
    }

    public function testMembershipCreatedEmailGetDefaultMailConf(): void
    {
        $email = new MembershipCreatedEmail(
            $this->makeSender(),
            $this->makeMembershipWithTeam(),
            $this->makeUserDao(),
            $this->makeTeamDao()
        );
        $conf = $email->_getDefaultMailConf();

        $this->assertIsArray($conf);
    }

    public function testMembershipCreatedEmailGetTemplateVariables(): void
    {
        $email = new MembershipCreatedEmail(
            $this->makeSender(),
            $this->makeMembershipWithTeam(),
            $this->makeUserDao(),
            $this->makeTeamDao()
        );
        $vars = $email->_getTemplateVariables();

        $this->assertArrayHasKey('user', $vars);
        $this->assertArrayHasKey('sender', $vars);
        $this->assertArrayHasKey('team', $vars);
        $this->assertArrayHasKey('manageUrl', $vars);
    }

    public function testMembershipDeletedEmailConstruction(): void
    {
        $email = new MembershipDeletedEmail($this->makeSender(), $this->makeUser(), $this->makeTeam('Removed Team'));
        $this->assertInstanceOf(MembershipDeletedEmail::class, $email);
    }

    public function testMembershipDeletedEmailGetTemplateVariables(): void
    {
        $email = new MembershipDeletedEmail($this->makeSender(), $this->makeUser(), $this->makeTeam());
        $method = new ReflectionMethod(MembershipDeletedEmail::class, '_getTemplateVariables');
        $vars = $method->invoke($email);

        $this->assertArrayHasKey('user', $vars);
        $this->assertArrayHasKey('sender', $vars);
        $this->assertArrayHasKey('team', $vars);
    }

    public function testMembershipDeletedEmailGetLayoutVariables(): void
    {
        $email = new MembershipDeletedEmail($this->makeSender(), $this->makeUser(), $this->makeTeam('Layout Team'));
        $method = new ReflectionMethod(MembershipDeletedEmail::class, '_getLayoutVariables');
        $vars = $method->invoke($email);

        $this->assertArrayHasKey('title', $vars);
        $this->assertStringContainsString('Layout Team', $vars['title']);
    }

    public function testMembershipCreatedEmailSendCallsDoSend(): void
    {
        $email = $this->getMockBuilder(MembershipCreatedEmail::class)
            ->setConstructorArgs(
                [$this->makeSender(), $this->makeMembershipWithTeam(), $this->makeUserDao(), $this->makeTeamDao()]
            )
            ->onlyMethods(['doSend'])
            ->getMock();

        $email->expects($this->once())
            ->method('doSend')
            ->willReturn(true);

        $email->send();
    }

    public function testMembershipDeletedEmailSendCallsDoSend(): void
    {
        $email = $this->getMockBuilder(MembershipDeletedEmail::class)
            ->setConstructorArgs([$this->makeSender(), $this->makeUser(), $this->makeTeam()])
            ->onlyMethods(['doSend'])
            ->getMock();

        $email->expects($this->once())
            ->method('doSend')
            ->willReturn(true);

        $email->send();
    }
}
