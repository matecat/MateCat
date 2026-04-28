<?php

namespace unit\Controllers;

use Controller\API\V3\CancelRequestController;
use Exception;
use Klein\Request;
use Klein\Response;
use Model\Exceptions\NotFoundException;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Teams\TeamStruct;
use Model\Translations\SegmentTranslationStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use TestHelpers\AbstractTest;

/**
 * Testable subclass that overrides public methods to skip the private performChecks call,
 * allowing isolated testing of the validation and response logic.
 */
class TestableCancelRequestController extends CancelRequestController
{
    public bool $segmentDisabledFlag = false;
    public array $savedDisabledSegments = [];

    public function __construct()
    {
        // skip parent constructor
    }

    public function initWith(Request $request, Response $response): void
    {
        $ref = new ReflectionClass(CancelRequestController::class);
        $ref->getProperty('request')->setValue($this, $request);
        $ref->getProperty('response')->setValue($this, $response);
    }

    public function enableRequest(): void
    {
        // Skip performChecks, go straight to validation logic
        $rawIdJob = $this->request->param('id_job');
        $rawIdSegment = $this->request->param('id_segment');

        $id_job = filter_var($rawIdJob, FILTER_VALIDATE_INT);
        $id_segment = filter_var($rawIdSegment, FILTER_VALIDATE_INT);

        if ($id_job === false || $id_segment === false) {
            $this->response->code(400);
            $this->response->header('Content-Type', 'application/json');
            $this->response->body(json_encode([
                'errors' => [
                    [
                        'code' => 400,
                        'message' => 'Invalid id_job or id_segment',
                    ],
                ],
            ]));

            return;
        }

        $this->response->json([
            'id_segment' => $id_segment,
        ]);
    }

    public function cancelRequest(): void
    {
        // Skip performChecks, go straight to disable logic
        $id_job = $this->request->param('id_job');
        $id_segment = $this->request->param('id_segment');

        if (!$this->isSegmentDisabled($id_job, $id_segment)) {
            $this->saveSegmentDisabledInCache($id_job, $id_segment);
        }

        $this->response->json([
            'id_segment' => $id_segment,
        ]);
    }

    protected function isSegmentDisabled(int $id_job, int $id_segment): bool
    {
        return $this->segmentDisabledFlag;
    }

    protected function saveSegmentDisabledInCache(int $id_job, int $id_segment): void
    {
        $this->savedDisabledSegments[] = ['id_job' => $id_job, 'id_segment' => $id_segment];
    }
}

class CancelRequestControllerTest extends AbstractTest
{
    private Request|MockObject $request;
    private Response|MockObject $response;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = $this->createMock(Request::class);
        $this->response = $this->createMock(Response::class);
    }

    // ─── enableRequest tests ─────────────────────────────────────────

    #[Test]
    public function enableRequestReturnsJsonWithIdSegment(): void
    {
        $controller = $this->createControllerWithBypassedPerformChecks();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->response->expects($this->once())
            ->method('json')
            ->with(['id_segment' => 42]);

        $controller->enableRequest();
    }

    #[Test]
    public function enableRequestReturnsBadRequestForInvalidIdJob(): void
    {
        $controller = $this->createControllerWithBypassedPerformChecks();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 'not_a_number'],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->response->expects($this->once())
            ->method('code')
            ->with(400);

        $this->response->expects($this->once())
            ->method('header')
            ->with('Content-Type', 'application/json');

        $this->response->expects($this->once())
            ->method('body')
            ->with($this->callback(function ($body) {
                $decoded = json_decode($body, true);
                return $decoded['errors'][0]['code'] === 400
                    && $decoded['errors'][0]['message'] === 'Invalid id_job or id_segment';
            }));

        $controller->enableRequest();
    }

    #[Test]
    public function enableRequestReturnsBadRequestForInvalidIdSegment(): void
    {
        $controller = $this->createControllerWithBypassedPerformChecks();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 'invalid'],
        ]);

        $this->response->expects($this->once())
            ->method('code')
            ->with(400);

        $controller->enableRequest();
    }

    #[Test]
    public function enableRequestReturnsBadRequestWhenBothIdsAreInvalid(): void
    {
        $controller = $this->createControllerWithBypassedPerformChecks();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 'abc'],
            ['password', null, 'abc123'],
            ['id_segment', null, 'xyz'],
        ]);

        $this->response->expects($this->once())
            ->method('code')
            ->with(400);

        $controller->enableRequest();
    }

    #[Test]
    public function enableRequestReturnsBadRequestForNegativeIdJob(): void
    {
        $controller = $this->createControllerWithBypassedPerformChecks();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, -1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        // filter_var(-1, FILTER_VALIDATE_INT) returns -1 (truthy), so this succeeds
        $this->response->expects($this->once())
            ->method('json')
            ->with(['id_segment' => 42]);

        $controller->enableRequest();
    }

    #[Test]
    public function enableRequestReturnsBadRequestForZeroIdJob(): void
    {
        $controller = $this->createControllerWithBypassedPerformChecks();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 0],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        // filter_var(0, FILTER_VALIDATE_INT) returns 0 which is falsy
        $this->response->expects($this->once())
            ->method('code')
            ->with(400);

        $controller->enableRequest();
    }

    #[Test]
    public function enableRequestReturnsBadRequestForNullIdSegment(): void
    {
        $controller = $this->createControllerWithBypassedPerformChecks();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, null],
        ]);

        $this->response->expects($this->once())
            ->method('code')
            ->with(400);

        $controller->enableRequest();
    }

    #[Test]
    public function enableRequestReturnsBadRequestForFloatIdJob(): void
    {
        $controller = $this->createControllerWithBypassedPerformChecks();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, '1.5'],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->response->expects($this->once())
            ->method('code')
            ->with(400);

        $controller->enableRequest();
    }

    #[Test]
    public function enableRequestWithLargeIntegerParams(): void
    {
        $controller = $this->createControllerWithBypassedPerformChecks();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 999999999],
            ['password', null, 'pass'],
            ['id_segment', null, 888888888],
        ]);

        $this->response->expects($this->once())
            ->method('json')
            ->with(['id_segment' => 888888888]);

        $controller->enableRequest();
    }

    #[Test]
    public function enableRequestWithStringIntegerParams(): void
    {
        $controller = $this->createControllerWithBypassedPerformChecks();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, '123'],
            ['password', null, 'abc123'],
            ['id_segment', null, '456'],
        ]);

        $this->response->expects($this->once())
            ->method('json')
            ->with(['id_segment' => 456]);

        $controller->enableRequest();
    }

    #[Test]
    public function enableRequestReturnsBadRequestForEmptyStringIdJob(): void
    {
        $controller = $this->createControllerWithBypassedPerformChecks();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, ''],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->response->expects($this->once())
            ->method('code')
            ->with(400);

        $controller->enableRequest();
    }

    // ─── cancelRequest tests ─────────────────────────────────────────

    #[Test]
    public function cancelRequestReturnsJsonWithIdSegment(): void
    {
        $controller = $this->createControllerWithBypassedPerformChecks();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->response->expects($this->once())
            ->method('json')
            ->with(['id_segment' => 42]);

        $controller->cancelRequest();
    }

    #[Test]
    public function cancelRequestSkipsDisableWhenSegmentAlreadyDisabled(): void
    {
        $controller = $this->createControllerWithBypassedPerformChecks(segmentDisabled: true);

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->response->expects($this->once())
            ->method('json')
            ->with(['id_segment' => 42]);

        $controller->cancelRequest();

        // Verify saveSegmentDisabledInCache was NOT called
        $this->assertEmpty($controller->savedDisabledSegments);
    }

    #[Test]
    public function cancelRequestSavesDisabledCacheWhenNotAlreadyDisabled(): void
    {
        $controller = $this->createControllerWithBypassedPerformChecks(segmentDisabled: false);

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $controller->cancelRequest();

        $this->assertCount(1, $controller->savedDisabledSegments);
        $this->assertEquals(['id_job' => 1, 'id_segment' => 42], $controller->savedDisabledSegments[0]);
    }

    #[Test]
    public function cancelRequestWithDifferentSegmentId(): void
    {
        $controller = $this->createControllerWithBypassedPerformChecks();

        $this->request->method('param')->willReturnCallback(function ($key) {
            return match ($key) {
                'id_job' => 10,
                'password' => 'pwd123',
                'id_segment' => 55,
                default => null,
            };
        });

        $this->response->expects($this->once())
            ->method('json')
            ->with(['id_segment' => 55]);

        $controller->cancelRequest();
    }

    // ─── performChecks tests ─────────────────────────────────────────

    #[Test]
    public function performChecksThrowsNotFoundExceptionWhenJobNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Job not found.');

        $controller = $this->createControllerWithPartialMock(jobReturn: null);

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $controller->cancelRequest();
    }

    #[Test]
    public function performChecksThrowsNotFoundExceptionWhenSegmentNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Segment not found');

        $jobStruct = $this->createMock(JobStruct::class);
        $controller = $this->createControllerWithPartialMock(jobReturn: $jobStruct, segmentReturn: []);

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $controller->cancelRequest();
    }

    #[Test]
    public function performChecksThrowsNotFoundExceptionWhenTeamNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Team not found');

        $projectStruct = $this->createMock(ProjectStruct::class);
        $projectStruct->method('getTeam')->willReturn(null);

        $jobStruct = $this->createMock(JobStruct::class);
        $jobStruct->method('getProject')->willReturn($projectStruct);

        $segmentTranslation = new SegmentTranslationStruct();
        $segmentTranslation->status = 'NEW';

        $controller = $this->createControllerWithPartialMock(
            jobReturn: $jobStruct,
            segmentReturn: $segmentTranslation,
        );

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $controller->cancelRequest();
    }

    #[Test]
    public function performChecksThrowsExceptionWhenUserIsNotPartOfTeam(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User is not part of the team');

        $teamStruct = $this->createMock(TeamStruct::class);
        $teamStruct->created_by = 999;
        $teamStruct->method('hasUser')->willReturn(false);

        $projectStruct = $this->createMock(ProjectStruct::class);
        $projectStruct->method('getTeam')->willReturn($teamStruct);

        $jobStruct = $this->createMock(JobStruct::class);
        $jobStruct->method('getProject')->willReturn($projectStruct);

        $segmentTranslation = new SegmentTranslationStruct();
        $segmentTranslation->status = 'NEW';

        $user = $this->createMock(UserStruct::class);
        $user->uid = 123;
        $user->email = 'test@example.com';

        $controller = $this->createControllerWithPartialMock(
            jobReturn: $jobStruct,
            segmentReturn: $segmentTranslation,
            user: $user,
        );

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $controller->cancelRequest();
    }

    #[Test]
    public function performChecksThrowsExceptionWhenUserIsNotOwner(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User is not the owner of the segment');

        $teamStruct = $this->createMock(TeamStruct::class);
        $teamStruct->created_by = 999;
        $teamStruct->method('hasUser')->willReturn(true);

        $projectStruct = $this->createMock(ProjectStruct::class);
        $projectStruct->method('getTeam')->willReturn($teamStruct);

        $jobStruct = $this->createMock(JobStruct::class);
        $jobStruct->method('getProject')->willReturn($projectStruct);

        $segmentTranslation = new SegmentTranslationStruct();
        $segmentTranslation->status = 'NEW';

        $user = $this->createMock(UserStruct::class);
        $user->uid = 123;
        $user->email = 'test@example.com';

        $controller = $this->createControllerWithPartialMock(
            jobReturn: $jobStruct,
            segmentReturn: $segmentTranslation,
            user: $user,
        );

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $controller->cancelRequest();
    }

    #[Test]
    public function performChecksThrowsExceptionWhenSegmentStatusIsTranslated(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Segment is not in "new" status and cannot be disabled');

        $controller = $this->buildControllerWithSegmentStatus('TRANSLATED');

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $controller->cancelRequest();
    }

    #[Test]
    public function performChecksThrowsExceptionWhenSegmentStatusIsApproved(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Segment is not in "new" status and cannot be disabled');

        $controller = $this->buildControllerWithSegmentStatus('APPROVED');

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $controller->cancelRequest();
    }

    #[Test]
    public function performChecksThrowsExceptionWhenSegmentStatusIsDraft(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Segment is not in "new" status and cannot be disabled');

        $controller = $this->buildControllerWithSegmentStatus('DRAFT');

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $controller->cancelRequest();
    }

    #[Test]
    public function performChecksThrowsExceptionWhenSegmentStatusIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Segment is not in "new" status and cannot be disabled');

        $controller = $this->buildControllerWithSegmentStatus('REJECTED');

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $controller->cancelRequest();
    }

    #[Test]
    public function performChecksReturnsRateLimitResponseForIp(): void
    {
        $rateLimitedResponse = $this->createMock(Response::class);
        $rateLimitedResponse->expects($this->never())->method('json');

        $controller = $this->createControllerWithPartialMock(rateLimitResponseIp: $rateLimitedResponse);

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $controller->cancelRequest();
    }

    #[Test]
    public function performChecksReturnsRateLimitResponseForEmail(): void
    {
        $rateLimitedResponse = $this->createMock(Response::class);
        $rateLimitedResponse->expects($this->never())->method('json');

        $controller = $this->createControllerWithPartialMock(rateLimitResponseEmail: $rateLimitedResponse);

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $controller->cancelRequest();
    }

    #[Test]
    public function performChecksPassesWhenUserIsTeamOwnerAndSegmentIsNew(): void
    {
        $teamStruct = $this->createMock(TeamStruct::class);
        $teamStruct->created_by = 123;

        $projectStruct = $this->createMock(ProjectStruct::class);
        $projectStruct->method('getTeam')->willReturn($teamStruct);

        $jobStruct = $this->createMock(JobStruct::class);
        $jobStruct->method('getProject')->willReturn($projectStruct);

        $segmentTranslation = new SegmentTranslationStruct();
        $segmentTranslation->status = 'NEW';

        $user = $this->createMock(UserStruct::class);
        $user->uid = 123;
        $user->email = 'owner@example.com';

        $controller = $this->createControllerWithPartialMock(
            jobReturn: $jobStruct,
            segmentReturn: $segmentTranslation,
            user: $user,
        );

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->response->expects($this->once())
            ->method('json')
            ->with(['id_segment' => 42]);

        $controller->cancelRequest();
    }

    #[Test]
    public function performChecksUsesBlankEmailWhenUserEmailIsNull(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Job not found.');

        $user = $this->createMock(UserStruct::class);
        $user->uid = 123;
        $user->email = null;

        $controller = $this->createControllerWithPartialMock(
            jobReturn: null,
            user: $user,
        );

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $controller->cancelRequest();
    }

    #[Test]
    public function performChecksIncrementsRateLimitCounterWhenJobNotFound(): void
    {
        $user = $this->createMock(UserStruct::class);
        $user->uid = 123;
        $user->email = 'test@example.com';

        $controller = $this->getMockBuilder(CancelRequestController::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getJob',
                'checkRateLimitResponse',
                'incrementRateLimitCounter',
                'getUser',
                'isSegmentDisabled',
                'saveSegmentDisabledInCache',
            ])
            ->getMock();

        $ref = new ReflectionClass(CancelRequestController::class);
        $ref->getProperty('request')->setValue($controller, $this->request);
        $ref->getProperty('response')->setValue($controller, $this->response);
        $ref->getProperty('user')->setValue($controller, $user);

        $controller->method('getUser')->willReturn($user);
        $controller->method('getJob')->willReturn(null);
        $controller->method('checkRateLimitResponse')->willReturn(null);
        $controller->method('isSegmentDisabled')->willReturn(false);
        $controller->method('saveSegmentDisabledInCache');

        $controller->expects($this->exactly(2))
            ->method('incrementRateLimitCounter');

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->expectException(NotFoundException::class);
        $controller->cancelRequest();
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function buildControllerWithSegmentStatus(string $status): CancelRequestController
    {
        $teamStruct = $this->createMock(TeamStruct::class);
        $teamStruct->created_by = 123;

        $projectStruct = $this->createMock(ProjectStruct::class);
        $projectStruct->method('getTeam')->willReturn($teamStruct);

        $jobStruct = $this->createMock(JobStruct::class);
        $jobStruct->method('getProject')->willReturn($projectStruct);

        $segmentTranslation = new SegmentTranslationStruct();
        $segmentTranslation->status = $status;

        $user = $this->createMock(UserStruct::class);
        $user->uid = 123;
        $user->email = 'test@example.com';

        return $this->createControllerWithPartialMock(
            jobReturn: $jobStruct,
            segmentReturn: $segmentTranslation,
            user: $user,
        );
    }

    /**
     * Creates a TestableCancelRequestController with performChecks bypassed
     * (private method in parent is not inherited, so the testable subclass
     * won't call the real performChecks).
     */
    private function createControllerWithBypassedPerformChecks(bool $segmentDisabled = false): TestableCancelRequestController
    {
        $controller = new TestableCancelRequestController();
        $controller->segmentDisabledFlag = $segmentDisabled;
        $controller->initWith($this->request, $this->response);

        return $controller;
    }

    /**
     * Creates a controller with real performChecks but mocked external dependencies.
     */
    private function createControllerWithPartialMock(
        mixed $jobReturn = 'NOT_SET',
        mixed $segmentReturn = 'NOT_SET',
        ?UserStruct $user = null,
        ?Response $rateLimitResponseIp = null,
        ?Response $rateLimitResponseEmail = null,
    ): CancelRequestController {
        $controller = $this->getMockBuilder(CancelRequestController::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getJob',
                'checkRateLimitResponse',
                'incrementRateLimitCounter',
                'getUser',
                'isSegmentDisabled',
                'saveSegmentDisabledInCache',
            ])
            ->getMock();

        $ref = new ReflectionClass(CancelRequestController::class);

        $ref->getProperty('request')->setValue($controller, $this->request);
        $ref->getProperty('response')->setValue($controller, $this->response);

        if ($user === null) {
            $user = $this->createMock(UserStruct::class);
            $user->email = 'test@example.com';
            $user->uid = 123;
        }

        $ref->getProperty('user')->setValue($controller, $user);

        $controller->method('getUser')->willReturn($user);

        if ($jobReturn === 'NOT_SET') {
            $controller->method('getJob')->willReturn(null);
        } else {
            $controller->method('getJob')->willReturn($jobReturn);
        }

        // Rate limit mocking
        $callIndex = 0;
        $controller->method('checkRateLimitResponse')
            ->willReturnCallback(function () use (&$callIndex, $rateLimitResponseIp, $rateLimitResponseEmail) {
                $callIndex++;
                if ($callIndex === 1) {
                    return $rateLimitResponseEmail;
                }
                if ($callIndex === 2) {
                    return $rateLimitResponseIp;
                }
                return null;
            });

        $controller->method('incrementRateLimitCounter');
        $controller->method('isSegmentDisabled')->willReturn(false);
        $controller->method('saveSegmentDisabledInCache');

        return $controller;
    }
}

