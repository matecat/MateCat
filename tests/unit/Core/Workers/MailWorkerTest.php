<?php

namespace Matecat\Core\Workers;

use Matecat\TestHelpers\AbstractTest;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use Model\DataAccess\Database;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\MailWorker;
use Utils\TaskRunner\Commons\Params;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;

#[AllowMockObjectsWithoutExpectations]
class MailWorkerTest extends AbstractTest
{
    private function createWorkerWithMailer(PHPMailer $mailer): MailWorker
    {
        $amq = $this->createStub(AMQHandler::class);

        $worker = $this->getMockBuilder(MailWorker::class)
            ->setConstructorArgs([$amq, obtainTestDatabase()])
            ->onlyMethods(['createMailer', '_checkDatabaseConnection', '_doLog'])
            ->getMock();

        $worker->method('createMailer')->willReturn($mailer);

        return $worker;
    }

    private function createQueueElement(): QueueElement
    {
        $params = new Params();
        $params->Host = 'smtp.test.com';
        $params->port = 587;
        $params->sender = 'sender@test.com';
        $params->hostname = 'test.com';
        $params->from = 'from@test.com';
        $params->fromName = 'Test Sender';
        $params->returnPath = 'return@test.com';
        $params->subject = 'Test Subject';
        $params->htmlBody = '<p>Hello</p>';
        $params->altBody = 'Hello';
        $params->address = ['recipient@test.com', 'Recipient'];

        $queueElement = new QueueElement();
        $queueElement->params = $params;
        $queueElement->reQueueNum = 0;

        return $queueElement;
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
    public function processThrowsReQueueOnSendFailure(): void
    {
        $mailer = $this->createStub(PHPMailer::class);
        $mailer->method('send')->willReturn(false);
        $mailer->ErrorInfo = 'SMTP connection failed';

        $worker = $this->createWorkerWithMailer($mailer);

        $this->expectException(ReQueueException::class);
        $worker->process($this->createQueueElement());
    }

    #[Test]
    public function processThrowsEndQueueOnEmptyAddress(): void
    {
        $mailer = $this->createStub(PHPMailer::class);

        $queueElement = $this->createQueueElement();
        $queueElement->params->address = [null, null];

        $worker = $this->createWorkerWithMailer($mailer);

        $this->expectException(EndQueueException::class);
        $worker->process($queueElement);
    }
}
