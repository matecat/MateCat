<?php

namespace Matecat\Core\Utils\Email;

use Matecat\TestHelpers\AbstractTest;
use Model\Comments\CommentStruct;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Utils\Email\CommentEmail;
use Utils\Email\CommentMentionEmail;
use Utils\Email\CommentResolveEmail;

class CommentEmailTest extends AbstractTest
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
        $user->email = 'recipient@example.com';
        $user->first_name = 'John';
        $user->last_name = 'Doe';

        return $user;
    }

    private function makeComment(): CommentStruct
    {
        $comment = new CommentStruct();
        $comment->message = 'Test comment message';
        $comment->first_name = 'Jane';
        $comment->last_name = 'Smith';
        $comment->full_name = 'Jane Smith';
        $comment->id_segment = 42;

        return $comment;
    }

    private function makeProject(): ShapelessConcreteStruct
    {
        $project = new ShapelessConcreteStruct();
        $project->name = 'Test Project';
        $project->id = 1;

        return $project;
    }

    private function makeJob(): JobStruct
    {
        return new JobStruct([
            'id' => 100,
            'password' => 'abc123',
            'source' => 'en-US',
            'target' => 'it-IT',
        ]);
    }

    #[Test]
    public function commentEmailSendCallsDoSend(): void
    {
        $email = $this->getMockBuilder(CommentEmail::class)
            ->setConstructorArgs([$this->makeUser(), $this->makeComment(), 'https://example.com/translate', $this->makeProject(), $this->makeJob()])
            ->onlyMethods(['doSend'])
            ->getMock();

        $email->expects($this->once())
            ->method('doSend')
            ->willReturn(true);

        $email->send();
    }

    #[Test]
    public function commentMentionEmailSendCallsDoSend(): void
    {
        $email = $this->getMockBuilder(CommentMentionEmail::class)
            ->setConstructorArgs([$this->makeUser(), $this->makeComment(), 'https://example.com/translate', $this->makeProject(), $this->makeJob()])
            ->onlyMethods(['doSend'])
            ->getMock();

        $email->expects($this->once())
            ->method('doSend')
            ->willReturn(true);

        $email->send();
    }

    #[Test]
    public function commentResolveEmailSendCallsDoSend(): void
    {
        $email = $this->getMockBuilder(CommentResolveEmail::class)
            ->setConstructorArgs([$this->makeUser(), $this->makeComment(), 'https://example.com/translate', $this->makeProject(), $this->makeJob()])
            ->onlyMethods(['doSend'])
            ->getMock();

        $email->expects($this->once())
            ->method('doSend')
            ->willReturn(true);

        $email->send();
    }

    #[Test]
    public function commentEmailGetTemplateVariablesReturnsExpectedKeys(): void
    {
        $email = new CommentEmail($this->makeUser(), $this->makeComment(), 'https://example.com/translate', $this->makeProject(), $this->makeJob());
        $method = new ReflectionMethod(CommentEmail::class, '_getTemplateVariables');
        $vars = $method->invoke($email);

        $this->assertArrayHasKey('user', $vars);
        $this->assertArrayHasKey('project', $vars);
        $this->assertArrayHasKey('job', $vars);
        $this->assertArrayHasKey('commenter', $vars);
        $this->assertArrayHasKey('url', $vars);
        $this->assertArrayHasKey('content', $vars);
        $this->assertArrayHasKey('title', $vars);
        $this->assertArrayHasKey('action', $vars);
        $this->assertArrayHasKey('id_segment', $vars);
        $this->assertSame('New comment', $vars['title']);
        $this->assertStringContainsString('commented on', $vars['action']);
    }

    #[Test]
    public function commentMentionEmailSetsCorrectAction(): void
    {
        $email = new CommentMentionEmail($this->makeUser(), $this->makeComment(), 'https://example.com/translate', $this->makeProject(), $this->makeJob());
        $method = new ReflectionMethod(CommentMentionEmail::class, '_getTemplateVariables');
        $vars = $method->invoke($email);

        $this->assertSame('New mention on a comment', $vars['title']);
        $this->assertStringContainsString('mentioned you', $vars['action']);
    }

    #[Test]
    public function commentResolveEmailSetsCorrectAction(): void
    {
        $email = new CommentResolveEmail($this->makeUser(), $this->makeComment(), 'https://example.com/translate', $this->makeProject(), $this->makeJob());
        $method = new ReflectionMethod(CommentResolveEmail::class, '_getTemplateVariables');
        $vars = $method->invoke($email);

        $this->assertSame('Thread resolved', $vars['title']);
        $this->assertStringContainsString('resolved a thread', $vars['action']);
    }

    #[Test]
    public function urlIncludesCommentSuffix(): void
    {
        $email = new CommentEmail($this->makeUser(), $this->makeComment(), 'https://example.com/translate', $this->makeProject(), $this->makeJob());
        $method = new ReflectionMethod(CommentEmail::class, '_getTemplateVariables');
        $vars = $method->invoke($email);

        $this->assertStringEndsWith(',comment', $vars['url']);
    }
}
