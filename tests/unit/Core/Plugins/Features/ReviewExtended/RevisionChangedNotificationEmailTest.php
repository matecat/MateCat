<?php

namespace Matecat\Core\Plugins\Features\ReviewExtended;

use Matecat\TestHelpers\AbstractTest;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\ReviewExtended\Email\RevisionChangedNotificationEmail;

class RevisionChangedNotificationEmailTest extends AbstractTest
{
    private function makeRecipient(string $email = 'recipient@example.com', string $name = 'Recipient Name'): UserStruct
    {
        $user = $this->createStub(UserStruct::class);
        $user->email = $email;
        $user->method('fullName')->willReturn($name);
        $user->method('getEmail')->willReturn($email);
        $user->method('toArray')->willReturn(['email' => $email, 'name' => $name]);

        return $user;
    }

    private function makeAuthor(string $email = 'author@example.com'): UserStruct
    {
        $user = $this->createStub(UserStruct::class);
        $user->email = $email;
        $user->method('getEmail')->willReturn($email);
        $user->method('toArray')->willReturn(['email' => $email]);

        return $user;
    }

    #[Test]
    public function constructorSetsProperties(): void
    {
        $recipient = $this->makeRecipient();
        $segmentInfo = ['id_segment' => 1];
        $data = ['recipient' => $recipient, 'key' => 'value'];
        $url = 'https://example.com/segment/1';

        $email = new RevisionChangedNotificationEmail($segmentInfo, $data, $url);

        $this->assertInstanceOf(RevisionChangedNotificationEmail::class, $email);
    }

    #[Test]
    public function getTemplateVariablesReturnsExpectedKeys(): void
    {
        $recipient = $this->makeRecipient();
        $author = $this->makeAuthor();
        $segmentInfo = ['id_segment' => 1];
        $data = ['recipient' => $recipient];
        $url = 'https://example.com/segment/1';

        $email = new RevisionChangedNotificationEmail($segmentInfo, $data, $url, $author);

        $reflection = new \ReflectionMethod($email, '_getTemplateVariables');
        $vars = $reflection->invoke($email);

        $this->assertArrayHasKey('changeAuthor', $vars);
        $this->assertArrayHasKey('recipientUser', $vars);
        $this->assertArrayHasKey('segmentUrl', $vars);
        $this->assertArrayHasKey('data', $vars);
        $this->assertArrayHasKey('segmentInfo', $vars);
        $this->assertSame($url, $vars['segmentUrl']);
    }

    #[Test]
    public function getTemplateVariablesReturnsNullChangeAuthorWhenNotProvided(): void
    {
        $recipient = $this->makeRecipient();
        $segmentInfo = ['id_segment' => 1];
        $data = ['recipient' => $recipient];
        $url = 'https://example.com/segment/1';

        $email = new RevisionChangedNotificationEmail($segmentInfo, $data, $url);

        $reflection = new \ReflectionMethod($email, '_getTemplateVariables');
        $vars = $reflection->invoke($email);

        $this->assertNull($vars['changeAuthor']);
    }

    #[Test]
    public function sendCallsSendToWhenRecipientIsNotAuthor(): void
    {
        $recipient = $this->makeRecipient('recipient@example.com', 'Recipient Name');
        $author = $this->makeAuthor('author@example.com');
        $data = ['recipient' => $recipient];

        $email = $this->getMockBuilder(RevisionChangedNotificationEmail::class)
            ->setConstructorArgs([['id_segment' => 1], $data, 'https://example.com', $author])
            ->onlyMethods(['sendTo'])
            ->getMock();

        $email->expects($this->once())
            ->method('sendTo')
            ->with('recipient@example.com', 'Recipient Name');

        $email->send();
    }

    #[Test]
    public function sendSkipsWhenRecipientIsAuthor(): void
    {
        $recipient = $this->makeRecipient('same@example.com', 'Same Person');
        $author = $this->makeAuthor('same@example.com');
        $data = ['recipient' => $recipient];

        $email = $this->getMockBuilder(RevisionChangedNotificationEmail::class)
            ->setConstructorArgs([['id_segment' => 1], $data, 'https://example.com', $author])
            ->onlyMethods(['sendTo'])
            ->getMock();

        $email->expects($this->never())
            ->method('sendTo');

        $email->send();
    }

    #[Test]
    public function sendSkipsWhenRecipientEmailIsNull(): void
    {
        $recipient = $this->createStub(UserStruct::class);
        $recipient->email = null;
        $recipient->method('toArray')->willReturn([]);
        $data = ['recipient' => $recipient];

        $email = $this->getMockBuilder(RevisionChangedNotificationEmail::class)
            ->setConstructorArgs([['id_segment' => 1], $data, 'https://example.com'])
            ->onlyMethods(['sendTo'])
            ->getMock();

        $email->expects($this->never())
            ->method('sendTo');

        $email->send();
    }

    #[Test]
    public function sendCallsSendToWhenNoChangeAuthor(): void
    {
        $recipient = $this->makeRecipient('recipient@example.com', 'Recipient Name');
        $data = ['recipient' => $recipient];

        $email = $this->getMockBuilder(RevisionChangedNotificationEmail::class)
            ->setConstructorArgs([['id_segment' => 1], $data, 'https://example.com'])
            ->onlyMethods(['sendTo'])
            ->getMock();

        $email->expects($this->once())
            ->method('sendTo')
            ->with('recipient@example.com', 'Recipient Name');

        $email->send();
    }
}
