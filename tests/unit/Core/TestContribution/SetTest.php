<?php

namespace Matecat\Core\TestContribution;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\CapturesNamedLogs;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use Stomp\Exception\ConnectionException;
use Utils\ActiveMQ\AMQHandler;
use Utils\ActiveMQ\WorkerClient;
use Utils\Contribution\Set;
use Utils\Contribution\SetContributionRequest;

class SetTest extends AbstractTest
{
    use CapturesNamedLogs;

    /** Collects every record written to the 'engines' logger during a test. */
    private TestHandler $logHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logHandler = $this->captureLog('engines');
    }

    protected function tearDown(): void
    {
        // Put back a harmless handler, so the broker mock does not leak into the next test.
        disableAmqWorkerClientHelper();

        parent::tearDown();
    }

    /**
     * When the broker is down, Set::contribution() logs the failure as an error on the
     * 'engines' logger and re-throws the exception to the caller.
     *
     * @throws Exception
     * @throws MockObjectException
     */
    #[Test]
    public function contributionLogsTheErrorAndRethrowsWhenTheBrokerIsDown(): void
    {
        $this->brokerIsDown();

        try {
            Set::contribution($this->request());
            $this->fail('The broker exception must reach the caller');
        } catch (ConnectionException $e) {
            $this->assertSame('Could not connect to localhost:61613', $e->getMessage());
        }

        $this->assertTrue($this->logHandler->hasRecordThatContains('SetContribution failed', Level::Error));
        $this->assertTrue($this->logHandler->hasRecordThatContains('Could not connect to localhost:61613', Level::Error));
    }

    /**
     * Same as above, for the MT contribution queue.
     *
     * @throws Exception
     * @throws MockObjectException
     */
    #[Test]
    public function contributionMTLogsTheErrorAndRethrowsWhenTheBrokerIsDown(): void
    {
        $this->brokerIsDown();

        try {
            Set::contributionMT($this->request());
            $this->fail('The broker exception must reach the caller');
        } catch (ConnectionException $e) {
            $this->assertSame('Could not connect to localhost:61613', $e->getMessage());
        }

        $this->assertTrue($this->logHandler->hasRecordThatContains('SetContribution failed', Level::Error));
        $this->assertTrue($this->logHandler->hasRecordThatContains('Could not connect to localhost:61613', Level::Error));
    }

    /**
     * With no request there is nothing to send: contributionMT() returns without touching the
     * broker, so nothing is logged.
     *
     * @throws Exception
     * @throws MockObjectException
     */
    #[Test]
    public function contributionMTDoesNothingWithoutARequest(): void
    {
        $handler = $this->createMock(AMQHandler::class);
        $handler->expects($this->never())->method('publishToQueues');
        WorkerClient::init($handler);

        Set::contributionMT();

        $this->assertFalse($this->logHandler->hasErrorRecords());
    }

    /**
     * Simulate a broker that is down: every publish throws.
     *
     * @throws MockObjectException
     */
    private function brokerIsDown(): void
    {
        $handler = $this->createStub(AMQHandler::class);
        $handler->method('publishToQueues')->willThrowException(new ConnectionException('Could not connect to localhost:61613'));
        WorkerClient::init($handler);
    }

    private function request(): SetContributionRequest
    {
        $request = new SetContributionRequest();
        $request->id_job = 9_938_101;
        $request->id_segment = 9_938_102;

        return $request;
    }
}
