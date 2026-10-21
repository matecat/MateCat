<?php

namespace Matecat\Core\TestContribution;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\CapturesNamedLogs;
use Monolog\Level;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use Stomp\Exception\ConnectionException;
use Utils\ActiveMQ\AMQHandler;
use Utils\ActiveMQ\WorkerClient;
use Utils\Contribution\Get;
use Utils\Contribution\GetContributionRequest;

class GetTest extends AbstractTest
{
    use CapturesNamedLogs;

    protected function tearDown(): void
    {
        // Put back a harmless handler, so the failing stub does not leak into the next test.
        disableAmqWorkerClientHelper();

        parent::tearDown();
    }

    /**
     * When the broker is down, Get::contribution() logs the failure as an error on the
     * 'engines' logger and re-throws the exception to the caller.
     *
     * @throws Exception
     * @throws MockObjectException
     */
    #[Test]
    public function contributionLogsTheErrorAndRethrowsWhenTheBrokerIsDown(): void
    {
        $log = $this->captureLog('engines');

        $handler = $this->createStub(AMQHandler::class);
        $handler->method('publishToQueues')->willThrowException(new ConnectionException('Could not connect to localhost:61613'));
        WorkerClient::init($handler);

        $request = new GetContributionRequest();
        $request->id_job = 9_938_001;

        try {
            Get::contribution($request);
            $this->fail('The broker exception must reach the caller');
        } catch (ConnectionException $e) {
            $this->assertSame('Could not connect to localhost:61613', $e->getMessage());
        }

        $this->assertTrue($log->hasRecordThatContains('GetContribution failed', Level::Error));
        $this->assertTrue($log->hasRecordThatContains('Could not connect to localhost:61613', Level::Error));
    }
}
