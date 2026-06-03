<?php

namespace Matecat\Core\Workers;

use Matecat\TestHelpers\AbstractTest;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\ErrMailWorker;
use Utils\TaskRunner\Commons\Params;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EmptyElementException;
use Utils\TaskRunner\Exceptions\ReQueueException;

#[AllowMockObjectsWithoutExpectations]
class ErrMailWorkerTest extends AbstractTest
{
    private function createWorkerWithMailer(PHPMailer $mailer): ErrMailWorker
    {
        $amq = $this->createStub(AMQHandler::class);

        $worker = $this->getMockBuilder(ErrMailWorker::class)
            ->setConstructorArgs([$amq])
            ->onlyMethods(['createMailer', '_checkDatabaseConnection', '_doLog'])
            ->getMock();

        $worker->method('createMailer')->willReturn($mailer);

        return $worker;
    }

    private function createQueueElement(): QueueElement
    {
        $params = new Params();
        $params->server_configuration = [
            'Host' => 'smtp.test.com',
            'Port' => 587,
            'Sender' => 'sender@test.com',
            'Hostname' => 'test.com',
            'From' => 'from@test.com',
            'FromName' => 'Test',
            'ReturnPath' => 'return@test.com',
        ];
        $params->email_list = ['admin@test.com' => 'Admin'];
        $params->subject = 'Test Error';
        $params->body = '<p>Error occurred</p>';

        $queueElement = new QueueElement();
        $queueElement->params = $params;
        $queueElement->reQueueNum = 0;

        return $queueElement;
    }

    #[Test]
    public function getLoggerNameReturnsExpected(): void
    {
        $amq = $this->createStub(AMQHandler::class);
        $worker = new ErrMailWorker($amq);

        $this->assertSame('err_mail.log', $worker->getLoggerName());
    }

    #[Test]
    public function processSuccessfullySendsEmail(): void
    {
        $mailer = $this->createStub(PHPMailer::class);
        $mailer->method('send')->willReturn(true);

        $worker = $this->createWorkerWithMailer($mailer);
        $worker->process($this->createQueueElement());

        $this->assertTrue(true);
    }

    #[Test]
    public function processThrowsEmptyElementOnNoServerConfig(): void
    {
        $mailer = $this->createStub(PHPMailer::class);
        $worker = $this->createWorkerWithMailer($mailer);

        $queueElement = $this->createQueueElement();
        $queueElement->params->server_configuration = null;

        $this->expectException(EmptyElementException::class);
        $worker->process($queueElement);
    }

    #[Test]
    public function processThrowsEmptyElementOnNoEmailList(): void
    {
        $mailer = $this->createStub(PHPMailer::class);
        $worker = $this->createWorkerWithMailer($mailer);

        $queueElement = $this->createQueueElement();
        $queueElement->params->email_list = [];

        $this->expectException(EmptyElementException::class);
        $worker->process($queueElement);
    }

    #[Test]
    public function processThrowsReQueueOnSendFailure(): void
    {
        $mailer = $this->createStub(PHPMailer::class);
        $mailer->method('send')->willReturn(false);
        $mailer->ErrorInfo = 'SMTP failed';

        $worker = $this->createWorkerWithMailer($mailer);

        $this->expectException(ReQueueException::class);
        $worker->process($this->createQueueElement());
    }

    #[Test]
    public function processUsesDefaultSubjectWhenEmpty(): void
    {
        $mailer = $this->createStub(PHPMailer::class);
        $mailer->method('send')->willReturn(true);

        $worker = $this->createWorkerWithMailer($mailer);

        $queueElement = $this->createQueueElement();
        $queueElement->params->subject = null;

        $worker->process($queueElement);

        $this->assertTrue(true);
    }
}
