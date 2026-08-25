<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\GetContributionController;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Utils\Constants\SourcePages;
use Utils\Contribution\GetContributionRequest;
use Matecat\SubFiltering\Enum\InjectableFiltersTags;
use Matecat\SubFiltering\MateCatFilter;
use Model\Jobs\JobStruct;
use Model\Segments\SegmentStruct;
use Model\Jobs\MetadataDao;

class TestableGetContributionController extends GetContributionController
{
    public ?GetContributionRequest $capturedRequest = null;

    public function __construct()
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

        obtainTestDatabase()->begin();

        // Insert fake user matching job owner so getProjectOwner() can resolve
        $conn = obtainTestDatabase()->getConnection();
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

        $this->reflector->getProperty('database')->setValue($this->controller, obtainTestDatabase());

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
        $conn = obtainTestDatabase()->getConnection();
        if ($conn->inTransaction()) {
            obtainTestDatabase()->rollback();
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
        $this->assertTrue($result['switch_languages']);
        $this->assertSame(['before1', 'before2'], $result['context_list_before']);
        $this->assertSame(['after1'], $result['context_list_after']);

        // password/received_password are no longer parsed here: ChunkPasswordValidator resolves the
        // credential and the job password is read from the credential-resolved chunk in get().
        $this->assertArrayNotHasKey('password', $result);
        $this->assertArrayNotHasKey('received_password', $result);
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
        $ref->getProperty('database')->setValue($testable, obtainTestDatabase());

        $featureSet = new FeatureSet(obtainTestDatabase());
        $ref->getProperty('featureSet')->setValue($testable, $featureSet);

        $user = new UserStruct();
        $user->uid = 1886472050;
        $user->email = 'foo@example.org';
        $ref->getProperty('user')->setValue($testable, $user);

        // ChunkPasswordValidator runs before get() in production; here get() is called directly, so
        // seed the credential-resolved chunk the controller now reads instead of fetching by password.
        $chunk = (new JobDao(obtainTestDatabase()))->getByIdAndPassword(1886428338, 'a90acf203402');
        self::assertNotNull($chunk);
        $chunk->setSourcePage(SourcePages::SOURCE_PAGE_TRANSLATE);
        $ref->getProperty('chunk')->setValue($testable, $chunk);

        $testable->get();

        $this->assertNotNull($testable->capturedRequest);
        $this->assertSame(1886428338, $testable->capturedRequest->id_job);
        $this->assertSame('a90acf203402', $testable->capturedRequest->password);
        $this->assertSame('test-client-1', $testable->capturedRequest->id_client);
        $this->assertTrue($testable->capturedRequest->concordanceSearch);
        $this->assertSame(10, $testable->capturedRequest->resultNum);
        // No stored segment is read on this branch, so no source can be inspected and the
        // project handlers must reach the TM exactly as the job declares them.
        $this->assertSame(
            (new MetadataDao(obtainTestDatabase()))->getSubfilteringCustomHandlers(1886428338, 'a90acf203402'),
            $testable->capturedRequest->subfiltering_handlers
        );
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
        $ref->getProperty('database')->setValue($testable, obtainTestDatabase());

        $featureSet = new FeatureSet(obtainTestDatabase());
        $ref->getProperty('featureSet')->setValue($testable, $featureSet);

        $user = new UserStruct();
        $user->uid = 1886472050;
        $user->email = 'foo@example.org';
        $ref->getProperty('user')->setValue($testable, $user);

        // ChunkPasswordValidator runs before get() in production; here get() is called directly, so
        // seed the credential-resolved chunk the controller now reads instead of fetching by password.
        $chunk = (new JobDao(obtainTestDatabase()))->getByIdAndPassword(1886428338, 'a90acf203402');
        self::assertNotNull($chunk);
        $chunk->setSourcePage(SourcePages::SOURCE_PAGE_TRANSLATE);
        $ref->getProperty('chunk')->setValue($testable, $chunk);

        $testable->get();

        $this->assertNotNull($testable->capturedRequest);
        $this->assertSame(1886428338, $testable->capturedRequest->id_job);
        $this->assertFalse($testable->capturedRequest->concordanceSearch);
        $this->assertNotEmpty($testable->capturedRequest->context_list_before);
        $this->assertNotEmpty($testable->capturedRequest->context_list_after);
        $this->assertInstanceOf(GetContributionRequest::class, $testable->capturedRequest);
        // The stored source of this segment carries no complex ICU, so the reduction must not
        // fire: the job handlers travel untouched.
        $this->assertSame(
            (new MetadataDao(obtainTestDatabase()))->getSubfilteringCustomHandlers(1886428338, 'a90acf203402'),
            $testable->capturedRequest->subfiltering_handlers
        );
    }

    // ──────────────────────────────────────────────────────────────────
    // ICU: the stored source decides the handler set, and the TM is told
    // ──────────────────────────────────────────────────────────────────

    /**
     * Complex ICU plural block plus a simple argument. The simple argument is the
     * discriminating part: the single-curly handler cannot match the plural block but it
     * does match {SEARCH_TERM}.
     */
    private const ICU_SEGMENT = 'You have {NUM_RESULTS, plural, one {1 result} other {# results}} for "{SEARCH_TERM}".';

    private function segmentsList(?string $segment, ?string $before = null, ?string $after = null): object
    {
        $wrap = static function (?string $text): ?SegmentStruct {
            if ($text === null) {
                return null;
            }
            $struct = new SegmentStruct();
            $struct->segment = $text;

            return $struct;
        };

        return (object)[
            'id_before' => $wrap($before),
            'id_segment' => $wrap($segment),
            'id_after' => $wrap($after),
        ];
    }

    private function chunk(): JobStruct
    {
        $chunk = new JobStruct();
        $chunk->source = 'en-US';
        $chunk->target = 'it-IT';

        return $chunk;
    }

    #[Test]
    public function segmentSourceContainsIcu_true_for_a_valid_complex_pattern(): void
    {
        $result = $this->invokeMethod('segmentSourceContainsIcu', [
            true, $this->chunk(), $this->segmentsList(self::ICU_SEGMENT)
        ]);

        $this->assertTrue($result);
    }

    #[Test]
    public function segmentSourceContainsIcu_false_for_a_bare_placeholder(): void
    {
        $result = $this->invokeMethod('segmentSourceContainsIcu', [
            true, $this->chunk(), $this->segmentsList('Hello {NAME}, welcome.')
        ]);

        $this->assertFalse($result);
    }

    #[Test]
    public function segmentSourceContainsIcu_false_when_the_project_flag_is_off(): void
    {
        $result = $this->invokeMethod('segmentSourceContainsIcu', [
            false, $this->chunk(), $this->segmentsList(self::ICU_SEGMENT)
        ]);

        $this->assertFalse($result);
    }

    /**
     * A concordance search reaches the dispatch with no stored segment behind it.
     */
    #[Test]
    public function segmentSourceContainsIcu_false_without_a_stored_segment(): void
    {
        $result = $this->invokeMethod('segmentSourceContainsIcu', [
            true, $this->chunk(), $this->segmentsList(null)
        ]);

        $this->assertFalse($result);
    }

    #[Test]
    public function contributionHandlers_reduces_the_wire_list_for_an_icu_segment(): void
    {
        $handlers = [
            InjectableFiltersTags::single_curly->value,
            InjectableFiltersTags::markup->value,
            InjectableFiltersTags::sprintf->value,
        ];

        $result = $this->invokeMethod('contributionHandlers', [$handlers, true]);

        $this->assertSame([InjectableFiltersTags::markup->value], $result);
    }

    /**
     * An empty reduction has to travel as null: an empty array is read as a request for the
     * default handlers, which is the opposite of keeping none.
     */
    #[Test]
    public function contributionHandlers_sends_null_when_no_handler_survives(): void
    {
        $result = $this->invokeMethod('contributionHandlers', [[InjectableFiltersTags::single_curly->value], true]);

        $this->assertNull($result);
    }

    #[Test]
    public function contributionHandlers_keeps_the_project_list_for_a_plain_segment(): void
    {
        $handlers = [InjectableFiltersTags::single_curly->value, InjectableFiltersTags::markup->value];

        $result = $this->invokeMethod('contributionHandlers', [$handlers, false]);

        $this->assertSame($handlers, $result);
    }

    #[Test]
    public function contributionHandlers_passes_the_no_handlers_sentinel_through(): void
    {
        $this->assertNull($this->invokeMethod('contributionHandlers', [null, false]));
        $this->assertNull($this->invokeMethod('contributionHandlers', [null, true]));
    }

    #[Test]
    public function subfilterContributionContexts_keeps_icu_when_the_filter_is_icu_compliant(): void
    {
        $request = ['text' => 'client sent this'];
        $filter = $this->filter(true);

        $this->invokeMethodByReference('subfilterContributionContexts', $request, [
            $filter, $this->segmentsList(self::ICU_SEGMENT, 'Before {TOKEN}', 'After')
        ]);

        $this->assertSame(self::ICU_SEGMENT, $request['text']);
        $this->assertStringNotContainsString('<ph ', $request['text']);
        // The contexts travel with the same reduced handler set, or the TM would decode them
        // with a list it was never given.
        $this->assertStringContainsString('{TOKEN}', $request['context_before']);
    }

    #[Test]
    public function subfilterContributionContexts_wraps_placeholders_for_a_plain_segment(): void
    {
        $request = ['text' => 'client sent this'];
        $filter = $this->filter(false);

        $this->invokeMethodByReference('subfilterContributionContexts', $request, [
            $filter, $this->segmentsList('Hello {NAME}, welcome.')
        ]);

        $this->assertStringContainsString('ctype="x-curly-brackets"', $request['text']);
        $this->assertStringNotContainsString('{NAME}', $request['text']);
    }

    /**
     * The invariant the whole wiring exists for: the list the TM is told must be the list the
     * text was actually built with. MyMemory decodes what it receives with the handlers it is
     * given and re-encodes its matches with them, so any drift between the two comes back as
     * ICU arguments wrapped in PH tags, and the Layer 1 to Layer 2 transition has no PH restore
     * to undo it. Reading the set back off the filter is what makes the two comparable.
     */
    #[Test]
    public function theWireListIsTheSetTheFilterActuallyResolved(): void
    {
        $handlers = [
            InjectableFiltersTags::single_curly->value,
            InjectableFiltersTags::markup->value,
            InjectableFiltersTags::sprintf->value,
        ];

        foreach ([true, false] as $sourceContainsIcu) {
            $filter = $this->filter($sourceContainsIcu);
            $wire = $this->invokeMethod('contributionHandlers', [$handlers, $sourceContainsIcu]);

            $this->assertEqualsCanonicalizing(
                $filter->getOrderedHandlerTagNames(),
                $wire ?? [],
                sprintf('handler set drifted from the filter (icu: %s)', var_export($sourceContainsIcu, true))
            );
        }
    }

    private function filter(bool $icuEnabled): MateCatFilter
    {
        /** @var MateCatFilter $filter */
        $filter = MateCatFilter::getInstance(
            null,
            'en-US',
            (string)json_encode(['it-IT']),
            [],
            [
                InjectableFiltersTags::single_curly->value,
                InjectableFiltersTags::markup->value,
                InjectableFiltersTags::sprintf->value,
            ],
            $icuEnabled
        );

        return $filter;
    }

    /**
     * @param array<string, mixed> $request
     * @param array<int, mixed> $args
     */
    private function invokeMethodByReference(string $name, array &$request, array $args): void
    {
        $method = $this->reflector->getMethod($name);
        $method->invokeArgs($this->controller, [&$request, ...$args]);
    }

}
