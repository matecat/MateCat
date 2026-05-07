<?php

namespace unit\Controllers;

use Controller\API\App\GetContributionController;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use TestHelpers\AbstractTest;
use Utils\Contribution\GetContributionRequest;

class TestableGetContributionController extends GetContributionController
{
    public ?GetContributionRequest $capturedRequest = null;

    public function __construct()
    {
    }

    protected function afterConstruct(): void
    {
    }

    protected function dispatchContribution(GetContributionRequest $contributionRequest): void
    {
        $this->capturedRequest = $contributionRequest;
    }
}

class GetContributionControllerTest extends AbstractTest
{
    private GetContributionController $controller;
    private ReflectionClass $reflector;
    private Request $requestMock;

    public function setUp(): void
    {
        parent::setUp();

        Database::obtain()->begin();

        // Insert fake user matching job owner so getProjectOwner() can resolve
        $conn = Database::obtain()->getConnection();
        $conn->exec(
            "INSERT IGNORE INTO users (uid, email, salt, pass, create_date, first_name, last_name)
             VALUES (1886472050, 'foo@example.org', 'x', 'x', '2024-01-01 00:00:00', 'Test', 'Owner')"
        );

        $this->requestMock = $this->createStub(Request::class);
        $responseMock = $this->createStub(Response::class);

        $this->reflector = new ReflectionClass(GetContributionController::class);
        $this->controller = $this->reflector->newInstanceWithoutConstructor();

        $requestProp = $this->reflector->getProperty('request');
        $requestProp->setValue($this->controller, $this->requestMock);

        $responseProp = $this->reflector->getProperty('response');
        $responseProp->setValue($this->controller, $responseMock);

        $featureSet = $this->createStub(FeatureSet::class);
        $featureSetProp = $this->reflector->getProperty('featureSet');
        $featureSetProp->setValue($this->controller, $featureSet);

        $user = new UserStruct();
        $user->uid = 42;
        $user->email = 'test@example.com';
        $userProp = $this->reflector->getProperty('user');
        $userProp->setValue($this->controller, $user);
    }

    public function tearDown(): void
    {
        $conn = Database::obtain()->getConnection();
        if ($conn->inTransaction()) {
            Database::obtain()->rollback();
        }

        parent::tearDown();
    }

    private function invokeMethod(string $name, array $args = []): mixed
    {
        $method = $this->reflector->getMethod($name);

        return $method->invokeArgs($this->controller, $args);
    }

    private function setupRequestParams(array $params): void
    {
        $this->requestMock->method('param')
            ->willReturnCallback(function (string $key) use ($params) {
                return $params[$key] ?? null;
            });
    }

    // ──────────────────────────────────────────────────────────────────
    // getCrossLanguages()
    // ──────────────────────────────────────────────────────────────────

    #[Test]
    public function getCrossLanguages_empty_array_returns_empty(): void
    {
        $result = $this->invokeMethod('getCrossLanguages', [[]]);
        $this->assertSame([], $result);
    }

    #[Test]
    public function getCrossLanguages_empty_string_returns_empty(): void
    {
        $result = $this->invokeMethod('getCrossLanguages', ['']);
        $this->assertSame([], $result);
    }

    #[Test]
    public function getCrossLanguages_single_language(): void
    {
        $result = $this->invokeMethod('getCrossLanguages', [['en-GB,']]);
        $this->assertSame(['en-GB'], $result);
    }

    #[Test]
    public function getCrossLanguages_multiple_languages(): void
    {
        $result = $this->invokeMethod('getCrossLanguages', [['en-GB,fr-FR,de-DE,']]);
        $this->assertSame(['en-GB', 'fr-FR', 'de-DE'], $result);
    }

    #[Test]
    public function getCrossLanguages_no_trailing_comma(): void
    {
        $result = $this->invokeMethod('getCrossLanguages', [['en-GB,fr-FR']]);
        $this->assertSame(['en-GB', 'fr-FR'], $result);
    }

    // ──────────────────────────────────────────────────────────────────
    // validateTheRequest() — missing required params
    // ──────────────────────────────────────────────────────────────────

    #[Test]
    public function validateTheRequest_missing_id_segment_in_non_concordance_throws(): void
    {
        $this->setupRequestParams([
            'id_client' => 'abc123',
            'id_job' => '999',
            'text' => 'Hello world',
            'password' => 'secret',
            'current_password' => 'pass',
            'is_concordance' => null,
            'id_segment' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing id_segment');
        $this->invokeMethod('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_missing_text_throws(): void
    {
        $this->setupRequestParams([
            'id_client' => 'abc123',
            'id_job' => '999',
            'id_segment' => '42',
            'text' => '',
            'password' => 'secret',
            'current_password' => 'pass',
            'is_concordance' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing text');
        $this->invokeMethod('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_text_zero_is_allowed(): void
    {
        $this->setupRequestParams([
            'id_client' => 'abc123',
            'id_job' => '999',
            'id_segment' => '42',
            'text' => '0',
            'password' => 'secret',
            'current_password' => 'pass',
            'is_concordance' => null,
            'num_results' => null,
            'translation' => null,
            'reasoning' => null,
            'from_target' => null,
            'context_before' => null,
            'context_after' => null,
            'context_list_before' => null,
            'context_list_after' => null,
            'id_before' => null,
            'id_after' => null,
            'cross_language' => null,
            'lara_style' => null,
        ]);

        $result = $this->invokeMethod('validateTheRequest');
        $this->assertSame('0', $result['text']);
    }

    #[Test]
    public function validateTheRequest_missing_id_job_throws(): void
    {
        $this->setupRequestParams([
            'id_client' => 'abc123',
            'id_job' => '',
            'id_segment' => '42',
            'text' => 'Hello',
            'password' => 'secret',
            'current_password' => 'pass',
            'is_concordance' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing id job');
        $this->invokeMethod('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_missing_password_throws(): void
    {
        $this->setupRequestParams([
            'id_client' => 'abc123',
            'id_job' => '999',
            'id_segment' => '42',
            'text' => 'Hello',
            'password' => '',
            'current_password' => 'pass',
            'is_concordance' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing job password');
        $this->invokeMethod('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_missing_id_client_throws(): void
    {
        $this->setupRequestParams([
            'id_client' => '',
            'id_job' => '999',
            'id_segment' => '42',
            'text' => 'Hello',
            'password' => 'secret',
            'current_password' => 'pass',
            'is_concordance' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing id_client');
        $this->invokeMethod('validateTheRequest');
    }

    // ──────────────────────────────────────────────────────────────────
    // validateTheRequest() — concordance search skips id_segment
    // ──────────────────────────────────────────────────────────────────

    #[Test]
    public function validateTheRequest_concordance_search_skips_id_segment_check(): void
    {
        $this->setupRequestParams([
            'id_client' => 'abc123',
            'id_job' => '999',
            'id_segment' => null,
            'text' => 'Hello',
            'password' => 'secret',
            'current_password' => 'pass',
            'is_concordance' => '1',
            'num_results' => null,
            'translation' => null,
            'reasoning' => null,
            'from_target' => null,
            'context_before' => null,
            'context_after' => null,
            'context_list_before' => null,
            'context_list_after' => null,
            'id_before' => null,
            'id_after' => null,
            'cross_language' => null,
            'lara_style' => null,
        ]);

        $result = $this->invokeMethod('validateTheRequest');
        $this->assertSame(999, $result['id_job']);
        $this->assertTrue($result['concordance_search']);
    }

    // ──────────────────────────────────────────────────────────────────
    // validateTheRequest() — successful validation returns correct types
    // ──────────────────────────────────────────────────────────────────

    #[Test]
    public function validateTheRequest_valid_request_returns_correct_structure(): void
    {
        $this->setupRequestParams([
            'id_client' => 'client-xyz',
            'id_job' => '123',
            'id_segment' => '456',
            'text' => ' Hello world ',
            'password' => 'jobpass',
            'current_password' => 'currpass',
            'is_concordance' => null,
            'num_results' => '3',
            'translation' => ' Ciao mondo ',
            'reasoning' => null,
            'from_target' => '1',
            'context_before' => 'ctx before',
            'context_after' => 'ctx after',
            'context_list_before' => '["before1","before2"]',
            'context_list_after' => '["after1"]',
            'id_before' => '455',
            'id_after' => '457',
            'cross_language' => null,
            'lara_style' => null,
        ]);

        $result = $this->invokeMethod('validateTheRequest');

        $this->assertSame('client-xyz', $result['id_client']);
        $this->assertSame(123, $result['id_job']);
        $this->assertSame('Hello world', $result['text']);
        $this->assertSame('Ciao mondo', $result['translation']);
        $this->assertSame('jobpass', $result['password']);
        $this->assertSame('currpass', $result['received_password']);
        $this->assertTrue($result['switch_languages']);
        $this->assertSame(['before1', 'before2'], $result['context_list_before']);
        $this->assertSame(['after1'], $result['context_list_after']);

        $idJobProp = $this->reflector->getProperty('id_job');
        $this->assertSame(123, $idJobProp->getValue($this->controller));

        $passProp = $this->reflector->getProperty('request_password');
        $this->assertSame('currpass', $passProp->getValue($this->controller));
    }

    #[Test]
    public function validateTheRequest_null_context_lists_returns_null(): void
    {
        $this->setupRequestParams([
            'id_client' => 'client-xyz',
            'id_job' => '123',
            'id_segment' => '456',
            'text' => 'Hello',
            'password' => 'jobpass',
            'current_password' => 'currpass',
            'is_concordance' => null,
            'num_results' => null,
            'translation' => null,
            'reasoning' => null,
            'from_target' => null,
            'context_before' => null,
            'context_after' => null,
            'context_list_before' => null,
            'context_list_after' => null,
            'id_before' => null,
            'id_after' => null,
            'cross_language' => null,
            'lara_style' => null,
        ]);

        $result = $this->invokeMethod('validateTheRequest');
        $this->assertNull($result['context_list_before']);
        $this->assertNull($result['context_list_after']);
    }

    #[Test]
    public function validateTheRequest_lara_style_validation(): void
    {
        $this->setupRequestParams([
            'id_client' => 'client-xyz',
            'id_job' => '123',
            'id_segment' => '456',
            'text' => 'Hello',
            'password' => 'jobpass',
            'current_password' => 'currpass',
            'is_concordance' => null,
            'num_results' => null,
            'translation' => null,
            'reasoning' => null,
            'from_target' => null,
            'context_before' => null,
            'context_after' => null,
            'context_list_before' => null,
            'context_list_after' => null,
            'id_before' => null,
            'id_after' => null,
            'cross_language' => null,
            'lara_style' => 'faithful',
        ]);

        $result = $this->invokeMethod('validateTheRequest');
        $this->assertSame('faithful', $result['lara_style']);
    }

    #[Test]
    public function validateTheRequest_invalid_lara_style_throws(): void
    {
        $this->setupRequestParams([
            'id_client' => 'client-xyz',
            'id_job' => '123',
            'id_segment' => '456',
            'text' => 'Hello',
            'password' => 'jobpass',
            'current_password' => 'currpass',
            'is_concordance' => null,
            'num_results' => null,
            'translation' => null,
            'reasoning' => null,
            'from_target' => null,
            'context_before' => null,
            'context_after' => null,
            'context_list_before' => null,
            'context_list_after' => null,
            'id_before' => null,
            'id_after' => null,
            'cross_language' => null,
            'lara_style' => 'nonexistent_style_xyz',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->invokeMethod('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_boolean_params_correctly_parsed(): void
    {
        $this->setupRequestParams([
            'id_client' => 'client-xyz',
            'id_job' => '123',
            'id_segment' => '456',
            'text' => 'Hello',
            'password' => 'jobpass',
            'current_password' => 'currpass',
            'is_concordance' => 'true',
            'num_results' => null,
            'translation' => null,
            'reasoning' => 'true',
            'from_target' => 'false',
            'context_before' => null,
            'context_after' => null,
            'context_list_before' => null,
            'context_list_after' => null,
            'id_before' => null,
            'id_after' => null,
            'cross_language' => null,
            'lara_style' => null,
        ]);

        $result = $this->invokeMethod('validateTheRequest');
        $this->assertTrue($result['concordance_search']);
        $this->assertTrue($result['reasoning']);
        $this->assertFalse($result['switch_languages']);
    }

    // ──────────────────────────────────────────────────────────────────
    // get() — integration test with real DB
    // ──────────────────────────────────────────────────────────────────

    #[Test]
    public function get_concordance_search_dispatches_contribution_request(): void
    {
        $testable = new TestableGetContributionController();
        $ref = new ReflectionClass(TestableGetContributionController::class);

        $requestStub = $this->createStub(Request::class);
        $requestStub->method('param')->willReturnCallback(function (string $key) {
            return match ($key) {
                'id_client' => 'test-client-1',
                'id_job' => '1886428338',
                'id_segment' => '1',
                'text' => 'Hello Hello world',
                'password' => 'a90acf203402',
                'current_password' => 'a90acf203402',
                'is_concordance' => '1',
                'num_results' => '3',
                'translation' => null,
                'reasoning' => null,
                'from_target' => null,
                'context_before' => null,
                'context_after' => null,
                'context_list_before' => null,
                'context_list_after' => null,
                'id_before' => null,
                'id_after' => null,
                'cross_language' => null,
                'lara_style' => null,
                default => null,
            };
        });

        $responseStub = $this->createStub(Response::class);
        $responseStub->method('json')->willReturn($responseStub);

        $ref->getProperty('request')->setValue($testable, $requestStub);
        $ref->getProperty('response')->setValue($testable, $responseStub);

        $featureSet = new FeatureSet();
        $ref->getProperty('featureSet')->setValue($testable, $featureSet);

        $user = new UserStruct();
        $user->uid = 1886472050;
        $user->email = 'foo@example.org';
        $ref->getProperty('user')->setValue($testable, $user);

        $testable->get();

        $this->assertNotNull($testable->capturedRequest);
        $this->assertSame(1886428338, $testable->capturedRequest->id_job);
        $this->assertSame('a90acf203402', $testable->capturedRequest->password);
        $this->assertSame('test-client-1', $testable->capturedRequest->id_client);
        $this->assertTrue($testable->capturedRequest->concordanceSearch);
        $this->assertSame(10, $testable->capturedRequest->resultNum);
    }

    #[Test]
    public function get_segment_contribution_dispatches_with_contexts(): void
    {
        $testable = new TestableGetContributionController();
        $ref = new ReflectionClass(TestableGetContributionController::class);

        $requestStub = $this->createStub(Request::class);
        $requestStub->method('param')->willReturnCallback(function (string $key) {
            return match ($key) {
                'id_client' => 'test-client-2',
                'id_job' => '1886428338',
                'id_segment' => '2',
                'text' => 'Hello world',
                'password' => 'a90acf203402',
                'current_password' => 'a90acf203402',
                'is_concordance' => null,
                'num_results' => null,
                'translation' => null,
                'reasoning' => null,
                'from_target' => null,
                'context_before' => 'before context',
                'context_after' => 'after context',
                'context_list_before' => '["ctx1"]',
                'context_list_after' => '["ctx2"]',
                'id_before' => '1',
                'id_after' => '3',
                'cross_language' => null,
                'lara_style' => null,
                default => null,
            };
        });

        $responseStub = $this->createStub(Response::class);
        $responseStub->method('json')->willReturn($responseStub);

        $ref->getProperty('request')->setValue($testable, $requestStub);
        $ref->getProperty('response')->setValue($testable, $responseStub);

        $featureSet = new FeatureSet();
        $ref->getProperty('featureSet')->setValue($testable, $featureSet);

        $user = new UserStruct();
        $user->uid = 1886472050;
        $user->email = 'foo@example.org';
        $ref->getProperty('user')->setValue($testable, $user);

        $testable->get();

        $this->assertNotNull($testable->capturedRequest);
        $this->assertSame(1886428338, $testable->capturedRequest->id_job);
        $this->assertFalse($testable->capturedRequest->concordanceSearch);
        $this->assertNotEmpty($testable->capturedRequest->context_list_before);
        $this->assertNotEmpty($testable->capturedRequest->context_list_after);
        $this->assertInstanceOf(GetContributionRequest::class, $testable->capturedRequest);
    }
}
