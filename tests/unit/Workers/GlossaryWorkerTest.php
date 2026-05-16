<?php

namespace unit\Workers;

use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use TestHelpers\AbstractTest;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\GlossaryWorker;
use Utils\Engines\MyMemory;
use Utils\Engines\Results\MyMemory\CheckGlossaryResponse;
use Utils\Engines\Results\MyMemory\DeleteGlossaryResponse;
use Utils\Engines\Results\MyMemory\DomainsResponse;
use Utils\Engines\Results\MyMemory\GetGlossaryResponse;
use Utils\Engines\Results\MyMemory\KeysGlossaryResponse;
use Utils\Engines\Results\MyMemory\SearchGlossaryResponse;
use Utils\Engines\Results\MyMemory\SetGlossaryResponse;
use Utils\Engines\Results\MyMemory\UpdateGlossaryResponse;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;

class TestableGlossaryWorker extends GlossaryWorker
{
    public MyMemory $mockClient;

    /** @var list<array<string, mixed>> */
    public array $publishedMessages = [];

    protected function getMyMemoryClient(): MyMemory
    {
        return $this->mockClient;
    }

    protected function publishToNodeJsClients(mixed $_object): void
    {
        $this->publishedMessages[] = $_object;
    }

    protected function _checkDatabaseConnection(): void
    {
    }
}

class GlossaryWorkerTest extends AbstractTest
{
    private TestableGlossaryWorker $worker;

    protected function setUp(): void
    {
        parent::setUp();

        $amqStub = $this->createStub(AMQHandler::class);
        $this->worker = new TestableGlossaryWorker($amqStub);

        $observerRef = new ReflectionProperty(AbstractWorker::class, '_observer');
        $observerRef->setValue($this->worker, []);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function processAction(string $action, array $payload): void
    {
        $queueElement = new QueueElement([
            'params' => [
                'action' => $action,
                'payload' => $payload,
            ],
        ]);

        $this->worker->process($queueElement);
    }

    /**
     * @return array{id: int, password: string}
     */
    private function jobData(): array
    {
        return ['id' => 42, 'password' => 'abc123'];
    }

    private function withStub(MyMemory $stub): void
    {
        $this->worker->mockClient = $stub;
    }

    private function withMock(MyMemory $mock): void
    {
        $this->worker->mockClient = $mock;
    }

    // ─────────────────────────────────────────────────────────────────
    // process()
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function processThrowsEndQueueExceptionForUnknownAction(): void
    {
        $this->withStub($this->createStub(MyMemory::class));

        $this->expectException(EndQueueException::class);
        $this->expectExceptionMessage('unknown_action is not an allowed action');

        $this->processAction('unknown_action', []);
    }

    // ─────────────────────────────────────────────────────────────────
    // check()
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function checkPublishesMatchesWithIdSegmentFromPayload(): void
    {
        $mock = $this->createMock(MyMemory::class);
        $response = new CheckGlossaryResponse([
            'matches' => ['terms' => ['foo']],
        ]);

        $mock->expects($this->once())
            ->method('glossaryCheck')
            ->with('hello', 'ciao', 'en-US', 'it-IT', ['key1'])
            ->willReturn($response);

        $this->withMock($mock);

        $this->processAction('check', [
            'source' => 'hello',
            'target' => 'ciao',
            'source_language' => 'en-US',
            'target_language' => 'it-IT',
            'keys' => ['key1'],
            'id_client' => 'client-1',
            'jobData' => $this->jobData(),
            'id_segment' => '99',
        ]);

        $this->assertCount(1, $this->worker->publishedMessages);
        $msg = $this->worker->publishedMessages[0];
        $this->assertSame('glossary_check', $msg['_type']);
        $this->assertSame('99', $msg['data']['payload']['id_segment']);
    }

    // ─────────────────────────────────────────────────────────────────
    // delete()
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function deletePassesIdJobAsStringToGlossaryDelete(): void
    {
        $mock = $this->createMock(MyMemory::class);
        $response = new DeleteGlossaryResponse([
            'responseStatus' => 200,
            'responseDetails' => '',
        ]);

        $mock->expects($this->once())
            ->method('glossaryDelete')
            ->with('seg-1', '42', 'pwd', ['term1'])
            ->willReturn($response);

        $this->withMock($mock);

        $this->processAction('delete', [
            'id_segment' => 'seg-1',
            'id_job' => 42,
            'password' => 'pwd',
            'term' => ['term1'],
            'id_client' => 'client-1',
            'jobData' => $this->jobData(),
        ]);

        $msg = $this->worker->publishedMessages[0];
        $this->assertSame('glossary_delete', $msg['_type']);
        $payload = $msg['data']['payload'];
        $this->assertSame('seg-1', $payload['id_segment']);
        $this->assertNotNull($payload['payload']);
    }

    #[Test]
    public function deletePublishesErrorWhenResponseStatusGte300(): void
    {
        $mock = $this->createMock(MyMemory::class);
        $response = new DeleteGlossaryResponse([
            'responseStatus' => 500,
            'responseDetails' => '',
        ]);

        $mock->expects($this->once())
            ->method('glossaryDelete')
            ->willReturn($response);

        $this->withMock($mock);

        $this->processAction('delete', [
            'id_segment' => 'seg-1',
            'id_job' => 42,
            'password' => 'pwd',
            'term' => ['term1'],
            'id_client' => 'client-1',
            'jobData' => $this->jobData(),
        ]);

        $msg = $this->worker->publishedMessages[0];
        $this->assertArrayHasKey('error', $msg['data']['payload']);
        $this->assertSame(500, $msg['data']['payload']['error']['code']);
    }

    // ─────────────────────────────────────────────────────────────────
    // domains()
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function domainsPublishesDomainEntries(): void
    {
        $mock = $this->createMock(MyMemory::class);
        $response = new DomainsResponse([
            'entries' => ['general' => true, 'legal' => false],
        ]);

        $mock->expects($this->once())
            ->method('glossaryDomains')
            ->with(['key1'])
            ->willReturn($response);

        $this->withMock($mock);

        $this->processAction('domains', [
            'source' => 'hello',
            'target' => 'ciao',
            'source_language' => 'en-US',
            'target_language' => 'it-IT',
            'keys' => ['key1'],
            'id_client' => 'client-1',
            'jobData' => $this->jobData(),
        ]);

        $msg = $this->worker->publishedMessages[0];
        $this->assertSame('glossary_domains', $msg['_type']);
        $this->assertSame(['general' => true, 'legal' => false], $msg['data']['payload']['entries']);
    }

    // ─────────────────────────────────────────────────────────────────
    // get()
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function getThrowsEndQueueExceptionWhenPayloadMissingRequiredFields(): void
    {
        $this->withStub($this->createStub(MyMemory::class));

        $this->expectException(EndQueueException::class);
        $this->expectExceptionMessage('Invalid Payload');

        $this->processAction('get', [
            'id_client' => 'client-1',
            'jobData' => $this->jobData(),
        ]);
    }

    #[Test]
    public function getPublishesFormattedMatches(): void
    {
        $mock = $this->createMock(MyMemory::class);
        $response = new GetGlossaryResponse([
            'matches' => ['id_segment' => '10', 'terms' => ['foo']],
        ]);

        $mock->expects($this->once())
            ->method('glossaryGet')
            ->willReturn($response);

        $this->withMock($mock);

        $this->processAction('get', [
            'id_segment' => '10',
            'source' => 'hello',
            'source_language' => 'en-US',
            'target_language' => 'it-IT',
            'id_job' => 42,
            'tmKeys' => [['key' => 'k1']],
            'id_client' => 'client-1',
            'jobData' => $this->jobData(),
        ]);

        $msg = $this->worker->publishedMessages[0];
        $this->assertSame('glossary_get', $msg['_type']);
        $this->assertSame('10', $msg['data']['payload']['id_segment']);
    }

    // ─────────────────────────────────────────────────────────────────
    // keys()
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function keysPublishesHasGlossaryResult(): void
    {
        $mock = $this->createMock(MyMemory::class);
        $response = new KeysGlossaryResponse([
            'entries' => ['key1' => true],
        ]);

        $mock->expects($this->once())
            ->method('glossaryKeys')
            ->with('en-US', 'it-IT', ['k1'])
            ->willReturn($response);

        $this->withMock($mock);

        $this->processAction('keys', [
            'source_language' => 'en-US',
            'target_language' => 'it-IT',
            'keys' => ['k1'],
            'id_client' => 'client-1',
            'jobData' => $this->jobData(),
        ]);

        $msg = $this->worker->publishedMessages[0];
        $this->assertSame('glossary_keys', $msg['_type']);
        $this->assertTrue($msg['data']['payload']['has_glossary']);
    }

    // ─────────────────────────────────────────────────────────────────
    // search()
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function searchPublishesFormattedMatches(): void
    {
        $mock = $this->createMock(MyMemory::class);
        $response = new SearchGlossaryResponse([
            'matches' => ['id_segment' => '10', 'terms' => ['bar']],
        ]);

        $mock->expects($this->once())
            ->method('glossarySearch')
            ->willReturn($response);

        $this->withMock($mock);

        $this->processAction('search', [
            'sentence' => 'hello world',
            'source_language' => 'en-US',
            'target_language' => 'it-IT',
            'tmKeys' => [['key' => 'k1']],
            'id_client' => 'client-1',
            'jobData' => $this->jobData(),
            'id_segment' => '10',
        ]);

        $msg = $this->worker->publishedMessages[0];
        $this->assertSame('glossary_search', $msg['_type']);
    }

    // ─────────────────────────────────────────────────────────────────
    // set()
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function setPublishesPayloadOnSuccess(): void
    {
        $mock = $this->createMock(MyMemory::class);
        $response = new SetGlossaryResponse([
            'responseStatus' => 200,
            'responseDetails' => 'req-123',
        ]);

        $mock->expects($this->once())
            ->method('glossarySet')
            ->willReturn($response);

        $this->withMock($mock);

        $this->processAction('set', [
            'id_segment' => 'seg-1',
            'id_job' => 42,
            'password' => 'pwd',
            'term' => [
                'matching_words' => ['foo', 'bar'],
                'metadata' => ['keys' => ['k1', 'k2']],
            ],
            'id_client' => 'client-1',
            'jobData' => $this->jobData(),
        ]);

        $msg = $this->worker->publishedMessages[0];
        $this->assertSame('glossary_set', $msg['_type']);
        $payload = $msg['data']['payload'];
        $this->assertNotNull($payload['payload']);
        $this->assertSame('req-123', $payload['payload']['request_id']);
    }

    #[Test]
    public function setPublishesErrorOnFailure(): void
    {
        $mock = $this->createMock(MyMemory::class);
        $response = new SetGlossaryResponse([
            'responseStatus' => 500,
            'responseDetails' => '',
        ]);

        $mock->expects($this->once())
            ->method('glossarySet')
            ->willReturn($response);

        $this->withMock($mock);

        $this->processAction('set', [
            'id_segment' => 'seg-1',
            'id_job' => 42,
            'password' => 'pwd',
            'term' => ['matching_words' => []],
            'id_client' => 'client-1',
            'jobData' => $this->jobData(),
        ]);

        $msg = $this->worker->publishedMessages[0];
        $this->assertArrayHasKey('error', $msg['data']['payload']);
        $this->assertSame(500, $msg['data']['payload']['error']['code']);
    }

    // ─────────────────────────────────────────────────────────────────
    // update()
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function updatePublishesPayloadOnSuccess(): void
    {
        $mock = $this->createMock(MyMemory::class);
        $response = new UpdateGlossaryResponse([
            'responseStatus' => 200,
            'responseDetails' => 'req-456',
        ]);

        $mock->expects($this->once())
            ->method('glossaryUpdate')
            ->willReturn($response);

        $this->withMock($mock);

        $this->processAction('update', [
            'id_segment' => 'seg-1',
            'id_job' => 42,
            'password' => 'pwd',
            'term' => ['matching_words' => ['foo']],
            'id_client' => 'client-1',
            'jobData' => $this->jobData(),
        ]);

        $msg = $this->worker->publishedMessages[0];
        $this->assertSame('glossary_update', $msg['_type']);
        $payload = $msg['data']['payload'];
        $this->assertNotNull($payload['payload']);
        $this->assertSame('req-456', $payload['payload']['request_id']);
        $this->assertArrayNotHasKey('error', $payload);
    }

    #[Test]
    public function updatePublishesBusyErrorWhenResponseStatus202(): void
    {
        $mock = $this->createMock(MyMemory::class);
        $response = new UpdateGlossaryResponse([
            'responseStatus' => 202,
            'responseDetails' => '',
        ]);

        $mock->expects($this->once())
            ->method('glossaryUpdate')
            ->willReturn($response);

        $this->withMock($mock);

        $this->processAction('update', [
            'id_segment' => 'seg-1',
            'id_job' => 42,
            'password' => 'pwd',
            'term' => ['matching_words' => ['foo']],
            'id_client' => 'client-1',
            'jobData' => $this->jobData(),
        ]);

        $msg = $this->worker->publishedMessages[0];
        $payload = $msg['data']['payload'];
        $this->assertArrayHasKey('error', $payload);
        $this->assertSame(202, $payload['error']['code']);
        $this->assertSame('MyMemory is busy, please try later', $payload['error']['message']);
        $this->assertNull($payload['payload']);
    }

    #[Test]
    public function updatePublishesGenericErrorWhenResponseStatusGte300(): void
    {
        $mock = $this->createMock(MyMemory::class);
        $response = new UpdateGlossaryResponse([
            'responseStatus' => 503,
            'responseDetails' => '',
        ]);

        $mock->expects($this->once())
            ->method('glossaryUpdate')
            ->willReturn($response);

        $this->withMock($mock);

        $this->processAction('update', [
            'id_segment' => 'seg-1',
            'id_job' => 42,
            'password' => 'pwd',
            'term' => ['matching_words' => ['foo']],
            'id_client' => 'client-1',
            'jobData' => $this->jobData(),
        ]);

        $msg = $this->worker->publishedMessages[0];
        $payload = $msg['data']['payload'];
        $this->assertArrayHasKey('error', $payload);
        $this->assertSame(503, $payload['error']['code']);
        $this->assertSame('Error, please try later', $payload['error']['message']);
    }

    // ─────────────────────────────────────────────────────────────────
    // formatGetGlossaryMatches()
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function getThrowsEndQueueExceptionWhenMatchesAreEmpty(): void
    {
        $mock = $this->createMock(MyMemory::class);
        $response = new GetGlossaryResponse(['matches' => []]);

        $mock->expects($this->once())
            ->method('glossaryGet')
            ->willReturn($response);

        $this->withMock($mock);

        $this->expectException(EndQueueException::class);
        $this->expectExceptionMessage('Empty response received from Glossary');

        $this->processAction('get', [
            'id_segment' => '10',
            'source' => 'hello',
            'source_language' => 'en-US',
            'target_language' => 'it-IT',
            'id_job' => 42,
            'tmKeys' => [['key' => 'k1']],
            'id_client' => 'client-1',
            'jobData' => $this->jobData(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // setResponsePayload()
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function responsePayloadStructureIsCorrect(): void
    {
        $stub = $this->createStub(MyMemory::class);
        $response = new KeysGlossaryResponse([
            'entries' => ['key1' => true],
        ]);

        $stub->method('glossaryKeys')->willReturn($response);
        $this->withStub($stub);

        $this->processAction('keys', [
            'source_language' => 'en-US',
            'target_language' => 'it-IT',
            'keys' => ['k1'],
            'id_client' => 'client-1',
            'jobData' => $this->jobData(),
        ]);

        $msg = $this->worker->publishedMessages[0];
        $this->assertArrayHasKey('_type', $msg);
        $this->assertArrayHasKey('data', $msg);
        $this->assertArrayHasKey('payload', $msg['data']);
        $this->assertArrayHasKey('id_client', $msg['data']);
        $this->assertArrayHasKey('id_job', $msg['data']);
        $this->assertArrayHasKey('passwords', $msg['data']);
        $this->assertSame('client-1', $msg['data']['id_client']);
        $this->assertSame(42, $msg['data']['id_job']);
        $this->assertSame('abc123', $msg['data']['passwords']);
    }
}
