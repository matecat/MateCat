<?php

namespace Matecat\Core\Utils\Email;

use Matecat\TestHelpers\AbstractTest;
use Model\Translators\JobsTranslatorsStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Utils\Email\SendToTranslatorAbstract;
use Utils\Email\SendToTranslatorForDeliveryChangeEmail;
use Utils\Email\SendToTranslatorForJobSplitEmail;
use Utils\Email\SendToTranslatorForNewJobEmail;

class SendToTranslatorEmailTest extends AbstractTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createDatabaseMock();
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        parent::tearDown();
    }

    private function makeUser(): UserStruct
    {
        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'owner@example.com';
        $user->first_name = 'Project';
        $user->last_name = 'Owner';

        return $user;
    }

    private function makeUserDao(): UserDao
    {
        [$dbStub] = $this->createDatabaseMock();
        return new UserDao($dbStub);
    }

    private function makeTranslator(): JobsTranslatorsStruct
    {
        $translator = new JobsTranslatorsStruct();
        $translator->email = 'translator@example.com';
        $translator->id_job = 100;
        $translator->job_password = 'pwd123';
        $translator->source = 'en-US';
        $translator->target = 'it-IT';
        $translator->delivery_date = '2026-06-15 10:00:00';
        $translator->added_by = 1;

        return $translator;
    }

    #[Test]
    public function newJobEmailSendCallsDoSend(): void
    {
        $email = $this->getMockBuilder(SendToTranslatorForNewJobEmail::class)
            ->setConstructorArgs([$this->makeUser(), $this->makeTranslator(), 'Test Project', $this->makeUserDao()])
            ->onlyMethods(['doSend'])
            ->getMock();

        $email->expects($this->once())
            ->method('doSend')
            ->willReturn(true);

        $email->send();
    }

    #[Test]
    public function deliveryChangeEmailSendCallsDoSend(): void
    {
        $email = $this->getMockBuilder(SendToTranslatorForDeliveryChangeEmail::class)
            ->setConstructorArgs([$this->makeUser(), $this->makeTranslator(), 'Test Project', $this->makeUserDao()])
            ->onlyMethods(['doSend'])
            ->getMock();

        $email->expects($this->once())
            ->method('doSend')
            ->willReturn(true);

        $email->send();
    }

    #[Test]
    public function jobSplitEmailSendCallsDoSend(): void
    {
        $email = $this->getMockBuilder(SendToTranslatorForJobSplitEmail::class)
            ->setConstructorArgs([$this->makeUser(), $this->makeTranslator(), 'Test Project', $this->makeUserDao()])
            ->onlyMethods(['doSend'])
            ->getMock();

        $email->expects($this->once())
            ->method('doSend')
            ->willReturn(true);

        $email->send();
    }

    #[Test]
    public function offsetToTimeZoneReturnsValidTimezone(): void
    {
        $email = new SendToTranslatorForNewJobEmail($this->makeUser(), $this->makeTranslator(), 'Test', $this->makeUserDao());
        $method = new ReflectionMethod(SendToTranslatorAbstract::class, '_offsetToTimeZone');
        $result = $method->invoke($email, 1);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function offsetToTimeZoneReturnsUtcForUnknownOffset(): void
    {
        $email = new SendToTranslatorForNewJobEmail($this->makeUser(), $this->makeTranslator(), 'Test', $this->makeUserDao());
        $method = new ReflectionMethod(SendToTranslatorAbstract::class, '_offsetToTimeZone');
        $result = $method->invoke($email, 99);

        $this->assertSame('UTC', $result);
    }

    #[Test]
    public function newJobEmailSetsCorrectTitle(): void
    {
        $email = new SendToTranslatorForNewJobEmail($this->makeUser(), $this->makeTranslator(), 'Test Project', $this->makeUserDao());

        $ref = new \ReflectionProperty($email, 'title');
        $this->assertSame('Matecat - Translation Job.', $ref->getValue($email));
    }

    #[Test]
    public function deliveryChangeEmailSetsCorrectTitle(): void
    {
        $email = new SendToTranslatorForDeliveryChangeEmail($this->makeUser(), $this->makeTranslator(), 'Test Project', $this->makeUserDao());

        $ref = new \ReflectionProperty($email, 'title');
        $this->assertSame('Matecat - Job delivery updated.', $ref->getValue($email));
    }
}
