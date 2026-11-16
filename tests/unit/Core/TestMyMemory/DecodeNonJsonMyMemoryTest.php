<?php

namespace Matecat\Core\TestMyMemory;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Utils\Engines\MyMemory;
use Utils\Engines\Results\ErrorResponse;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\Engines\Results\MyMemory\SetContributionResponse;
use Utils\Engines\Results\MyMemory\UpdateContributionResponse;

/**
 * A write answered with a body that is not a JSON object must be decoded as a 502 error,
 * not passed on as null. Lookups are not affected.
 *
 * Regression: during a broker outage MyMemory answered /update with HTTP 200 and the plain text
 * "Failed to connect to broker". json_decode() returned null, UpdateContributionResponse threw a
 * TypeError and the executor dropped the contribution instead of requeueing it.
 */
class DecodeNonJsonMyMemoryTest extends AbstractTest
{
    private MyMemory $myMemory;
    private ReflectionMethod $decode;

    public function setUp(): void
    {
        parent::setUp();

        $engineRecord = EngineStruct::getStruct();
        $engineRecord->id = 1;
        $engineRecord->name = 'MyMemory';
        $engineRecord->type = 'TM';
        $engineRecord->base_url = 'https://mymemory.example';
        $engineRecord->class_load = 'MyMemory';

        $this->myMemory = new MyMemory($engineRecord, $this->createStub(IDatabase::class));
        $this->decode = new ReflectionMethod($this->myMemory, '_decode');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonJsonBodies(): array
    {
        return [
            'broker failure text' => ['Failed to connect to broker'],
            'proxy html page'     => ['<html><body><h1>502 Bad Gateway</h1></body></html>'],
            'empty body'          => [''],
            'truncated json'      => ['{"responseData":"OK","responseStatus":200'],
            'json scalar'         => ['"OK"'],
        ];
    }

    #[Test]
    #[DataProvider('nonJsonBodies')]
    public function update_with_non_json_body_returns_502_error(string $body): void
    {
        $response = $this->decode->invoke($this->myMemory, $body, [], 'update_relative_url');

        $this->assertInstanceOf(UpdateContributionResponse::class, $response);
        $this->assertSame(502, $response->responseStatus);
        $this->assertInstanceOf(ErrorResponse::class, $response->error);
        $this->assertSame(-502, $response->error->code);
        // The start of the body stays in the message, so the log shows what MyMemory answered
        $this->assertStringContainsString(mb_substr($body, 0, 200), $response->error->message);
    }

    #[Test]
    public function update_error_keeps_only_the_start_of_a_long_body(): void
    {
        $body = str_repeat('x', 5000);

        $response = $this->decode->invoke($this->myMemory, $body, [], 'update_relative_url');

        $this->assertStringContainsString(str_repeat('x', 200), $response->error->message);
        $this->assertStringNotContainsString(str_repeat('x', 201), $response->error->message);
    }

    #[Test]
    #[DataProvider('nonJsonBodies')]
    public function contribute_with_non_json_body_returns_502_error(string $body): void
    {
        $response = $this->decode->invoke($this->myMemory, $body, [], 'contribute_relative_url');

        $this->assertInstanceOf(SetContributionResponse::class, $response);
        $this->assertSame(502, $response->responseStatus);
        $this->assertInstanceOf(ErrorResponse::class, $response->error);
    }

    #[Test]
    public function get_with_non_json_body_is_not_turned_into_an_error(): void
    {
        // Only writes are guarded: a lookup keeps its previous behaviour (empty result, no error)
        $response = $this->decode->invoke($this->myMemory, 'Failed to connect to broker', [], 'translate_relative_url');

        $this->assertInstanceOf(GetMemoryResponse::class, $response);
        $this->assertNull($response->error);
    }

    #[Test]
    public function update_with_json_body_is_unchanged(): void
    {
        $body = '{"responseData":"OK","responseStatus":200,"number_of_results":1,"segment_ids":["118b08e9-d437-eeba-e361-ab831a1e3f7f"]}';

        $response = $this->decode->invoke($this->myMemory, $body, [], 'update_relative_url');

        $this->assertInstanceOf(UpdateContributionResponse::class, $response);
        $this->assertSame(200, $response->responseStatus);
        $this->assertNull($response->error);
        $this->assertSame(['118b08e9-d437-eeba-e361-ab831a1e3f7f'], $response->responseDetails['segment_ids']);
    }
}
