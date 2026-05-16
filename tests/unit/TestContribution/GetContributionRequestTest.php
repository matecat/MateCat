<?php

use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Contribution\GetContributionRequest;

class GetContributionRequestTest extends AbstractTest
{
    #[Test]
    public function test_getJobStruct_always_returns_non_null(): void
    {
        $request = new GetContributionRequest();
        $result  = $request->getJobStruct();

        $this->assertInstanceOf(JobStruct::class, $result);
    }

    #[Test]
    public function test_getJobStruct_with_data_returns_populated_struct(): void
    {
        $request            = new GetContributionRequest();
        $request->jobStruct = ['id' => 42, 'source' => 'en-US', 'target' => 'it-IT', 'password' => 'abc123'];

        $result = $request->getJobStruct();

        $this->assertInstanceOf(JobStruct::class, $result);
        $this->assertEquals(42, $result->id);
        $this->assertEquals('en-US', $result->source);
        $this->assertEquals('it-IT', $result->target);
    }

    #[Test]
    public function test_getJobStruct_with_null_array_returns_empty_struct(): void
    {
        $request            = new GetContributionRequest();
        $request->jobStruct = null;

        $result = $request->getJobStruct();
        $this->assertInstanceOf(JobStruct::class, $result);
    }

    #[Test]
    public function test_getUser_always_returns_non_null(): void
    {
        $request = new GetContributionRequest();
        $result  = $request->getUser();

        $this->assertInstanceOf(UserStruct::class, $result);
    }

    #[Test]
    public function test_getUser_with_data_returns_populated_struct(): void
    {
        $request       = new GetContributionRequest();
        $request->user = ['uid' => 99, 'email' => 'test@example.com'];

        $result = $request->getUser();

        $this->assertInstanceOf(UserStruct::class, $result);
        $this->assertEquals(99, $result->uid);
        $this->assertEquals('test@example.com', $result->email);
    }

    #[Test]
    public function test_getProjectStruct_always_returns_non_null(): void
    {
        $request = new GetContributionRequest();
        $result  = $request->getProjectStruct();

        $this->assertInstanceOf(ProjectStruct::class, $result);
    }

    #[Test]
    public function test_getProjectStruct_with_data_returns_populated_struct(): void
    {
        $request               = new GetContributionRequest();
        $request->projectStruct = ['id' => 7, 'name' => 'Test Project'];

        $result = $request->getProjectStruct();

        $this->assertInstanceOf(ProjectStruct::class, $result);
        $this->assertEquals(7, $result->id);
    }

    #[Test]
    public function test_getContexts_returns_object_with_properties(): void
    {
        $request           = new GetContributionRequest();
        $request->contexts = [
            'segment'        => 'Hello world',
            'context_before' => 'Before text',
            'context_after'  => 'After text',
        ];

        $result = $request->getContexts();

        $this->assertIsObject($result);
        $this->assertEquals('Hello world', $result->segment);
        $this->assertEquals('Before text', $result->context_before);
        $this->assertEquals('After text', $result->context_after);
    }

    #[Test]
    public function test_getContexts_returns_null_properties_when_empty(): void
    {
        $request = new GetContributionRequest();

        $result = $request->getContexts();

        $this->assertIsObject($result);
        $this->assertNull($result->segment);
        $this->assertNull($result->context_before);
        $this->assertNull($result->context_after);
    }

    #[Test]
    public function test_getSessionId_returns_consistent_md5(): void
    {
        $request           = new GetContributionRequest();
        $request->id_file  = 10;
        $request->id_job   = 20;
        $request->password = 'secret';

        $expected = md5('10-20-secret');
        $this->assertEquals($expected, $request->getSessionId());
    }
}
