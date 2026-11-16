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

    /**
     * @param array<string, mixed> $fields more fields for toArray(), such as the name
     */
    private function makeAuthor(string $email = 'author@example.com', array $fields = []): UserStruct
    {
        $user = $this->createStub(UserStruct::class);
        $user->email = $email;
        $user->method('getEmail')->willReturn($email);
        $user->method('toArray')->willReturn(['email' => $email] + $fields);

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

    /**
     * The email sent when Federico Ricciuti moves an approved segment back to a lower status.
     *
     * @param list<array<string, mixed>> $issues
     */
    private function revisionEmail(array $issues = [], bool $isPreviousChangeAuthor = true, bool $withAuthor = true): RevisionChangedNotificationEmail
    {
        $segmentInfo = [
            'segment_source' => 'Ligurian',
            'old_translation' => 'Bahasa Liguria',
            'new_translation' => 'Bahasa Liguriatest',
            'old_status' => 'APPROVED2',
            'new_status' => 'APPROVED',
            'issues' => $issues,
        ];
        $data = ['recipient' => $this->makeRecipient(), 'isPreviousChangeAuthor' => $isPreviousChangeAuthor];
        $author = $withAuthor
            ? $this->makeAuthor('author@example.com', ['first_name' => 'Federico', 'last_name' => 'Ricciuti'])
            : null;

        return new RevisionChangedNotificationEmail($segmentInfo, $data, 'https://example.com/revise', $author);
    }

    private function render(RevisionChangedNotificationEmail $email, string $method = '_buildMessageContent', mixed ...$arguments): string
    {
        return (new \ReflectionMethod($email, $method))->invoke($email, ...$arguments);
    }

    #[Test]
    public function rendersTheIssuesAndTheirComments(): void
    {
        $html = $this->render($this->revisionEmail([
            [
                'category' => 'Style',
                'severity' => 'Minor',
                'comments' => [
                    ['source_page' => 1, 'create_date' => '2026-11-11 17:04:15', 'comment' => 'Check the tone'],
                    ['source_page' => 2, 'create_date' => '2026-11-11 17:10:00', 'comment' => 'Tone fixed'],
                ],
            ],
        ]));

        $this->assertStringContainsString('Federico Ricciuti', $html);
        $this->assertStringContainsString('changed a segment which was earlier approved by you.', $html);
        $this->assertStringContainsString('<strong>Issues</strong>', $html);
        $this->assertStringContainsString('Style <strong> [Minor]</strong>', $html);
        $this->assertMatchesRegularExpression('~Translator\s+\(Nov 11, 2026 05:04 PM\):\s+Check the tone~', $html);
        $this->assertMatchesRegularExpression('~Reviewer\s+\(Nov 11, 2026 05:10 PM\):\s+Tone fixed~', $html);
    }

    #[Test]
    public function rendersNoIssuesBlockWhenThereAreNone(): void
    {
        $html = $this->render($this->revisionEmail());

        $this->assertStringContainsString('<strong>New status</strong>: APPROVED', $html);
        $this->assertStringNotContainsString('<strong>Issues</strong>', $html);
    }

    #[Test]
    public function tellsAnotherRecipientTheSegmentWasPreviouslyApproved(): void
    {
        $html = $this->render($this->revisionEmail(isPreviousChangeAuthor: false));

        $this->assertStringContainsString('changed a previously approved segment.', $html);
        $this->assertStringNotContainsString('earlier approved by you', $html);
    }

    #[Test]
    public function namesSomeoneWhenTheAuthorIsUnknown(): void
    {
        $html = $this->render($this->revisionEmail(withAuthor: false));

        $this->assertMatchesRegularExpression('~<p>\s+someone\s+changed a segment~', $html);
    }

    #[Test]
    public function theLayoutDoesNotShowTheTitle(): void
    {
        $html = $this->render($this->revisionEmail(), '_buildHTMLMessage', '<p>body</p>');

        $this->assertStringContainsString('<p>body</p>', $html);
        $this->assertStringNotContainsString('<h1>Revised segment changed</h1>', $html);
        $this->assertStringNotContainsString('class="preheader"', $html);
    }
}
