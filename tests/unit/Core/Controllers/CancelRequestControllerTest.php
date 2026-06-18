<?php

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Exceptions\ConflictError;
use Controller\API\V3\CancelRequestController;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\Exceptions\NotFoundException;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Segments\SegmentDisabledService;
use Model\Teams\TeamDao;
use Model\Teams\TeamStruct;
use Model\Translations\SegmentTranslationDao;
use Model\Translations\SegmentTranslationStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use Utils\Constants\TranslationStatus;

#[AllowMockObjectsWithoutExpectations]
class CancelRequestControllerTest extends AbstractTest
{
    private Request|MockObject $request;
    private Response|MockObject $response;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = $this->createStub(Request::class);
        $this->response = $this->createMock(Response::class);
    }

    // ─── enableRequest tests ─────────────────────────────────────────

    #[Test]
    public function enableRequestReturnsJsonWithIdSegment(): void
    {
        $controller = $this->createActionController();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->response->method('code')->willReturn(200);
        $this->response->expects($this->once())
            ->method('json')
            ->with(['id_segment' => 42]);

        $controller->enableRequest();
    }

    #[Test]
    public function enableRequestReturnsBadRequestForInvalidIdJob(): void
    {
        $controller = $this->createActionController();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 'not_a_number'],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Invalid id_job or id_segment');

        $controller->enableRequest();
    }

    #[Test]
    public function enableRequestReturnsBadRequestForInvalidIdSegment(): void
    {
        $controller = $this->createActionController();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 'invalid'],
        ]);

        $this->expectException(NotFoundException::class);

        $controller->enableRequest();
    }

    #[Test]
    public function enableRequestReturnsBadRequestWhenBothIdsAreInvalid(): void
    {
        $controller = $this->createActionController();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 'abc'],
            ['password', null, 'abc123'],
            ['id_segment', null, 'xyz'],
        ]);

        $this->expectException(NotFoundException::class);

        $controller->enableRequest();
    }

    #[Test]
    public function enableRequestSucceedsWithNegativeIdJob(): void
    {
        $controller = $this->createActionController();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, -1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->response->method('code')->willReturn(200);
        $this->response->expects($this->once())
            ->method('json')
            ->with(['id_segment' => 42]);

        $controller->enableRequest();
    }

    #[Test]
    public function enableRequestSucceedsWithZeroIdJob(): void
    {
        $controller = $this->createActionController();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 0],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->response->method('code')->willReturn(200);
        $this->response->expects($this->once())
            ->method('json')
            ->with(['id_segment' => 42]);

        $controller->enableRequest();
    }

    #[Test]
    public function enableRequestReturnsBadRequestForNullIdSegment(): void
    {
        $controller = $this->createActionController();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, null],
        ]);

        $this->expectException(NotFoundException::class);

        $controller->enableRequest();
    }

    #[Test]
    public function enableRequestReturnsBadRequestForFloatIdJob(): void
    {
        $controller = $this->createActionController();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, '1.5'],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->expectException(NotFoundException::class);

        $controller->enableRequest();
    }

    #[Test]
    public function enableRequestWithLargeIntegerParams(): void
    {
        $controller = $this->createActionController();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 999999999],
            ['password', null, 'pass'],
            ['id_segment', null, 888888888],
        ]);

        $this->response->method('code')->willReturn(200);
        $this->response->expects($this->once())
            ->method('json')
            ->with(['id_segment' => 888888888]);

        $controller->enableRequest();
    }

    #[Test]
    public function enableRequestWithStringIntegerParams(): void
    {
        $controller = $this->createActionController();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, '123'],
            ['password', null, 'abc123'],
            ['id_segment', null, '456'],
        ]);

        $this->response->method('code')->willReturn(200);
        $this->response->expects($this->once())
            ->method('json')
            ->with(['id_segment' => 456]);

        $controller->enableRequest();
    }

    #[Test]
    public function enableRequestReturnsBadRequestForEmptyStringIdJob(): void
    {
        $controller = $this->createActionController();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, ''],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->expectException(NotFoundException::class);

        $controller->enableRequest();
    }

    #[Test]
    public function enableRequestCallsEnableWhenSegmentIsDisabled(): void
    {
        $service = $this->createMock(SegmentDisabledService::class);
        $service->method('isDisabled')->willReturn(true);
        $service->expects($this->once())->method('enable')->with(42);

        $controller = $this->createActionController(segmentDisabledService: $service);

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->response->method('code')->willReturn(200);

        $controller->enableRequest();
    }

    #[Test]
    public function enableRequestDoesNotCallEnableWhenSegmentIsNotDisabled(): void
    {
        $service = $this->createMock(SegmentDisabledService::class);
        $service->method('isDisabled')->willReturn(false);
        $service->expects($this->never())->method('enable');

        $controller = $this->createActionController(segmentDisabledService: $service);

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->response->method('code')->willReturn(200);

        $controller->enableRequest();
    }

    // ─── cancelRequest tests ─────────────────────────────────────────

    #[Test]
    public function cancelRequestReturnsJsonWithIdSegment(): void
    {
        $controller = $this->createActionController();

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->response->method('code')->willReturn(200);
        $this->response->expects($this->once())
            ->method('json')
            ->with(['id_segment' => 42]);

        $controller->cancelRequest();
    }

    #[Test]
    public function cancelRequestSkipsDisableWhenSegmentAlreadyDisabled(): void
    {
        $service = $this->createMock(SegmentDisabledService::class);
        $service->method('isDisabled')->willReturn(true);
        $service->expects($this->never())->method('disable');

        $controller = $this->createActionController(segmentDisabledService: $service);

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->response->method('code')->willReturn(200);

        $controller->cancelRequest();
    }

    #[Test]
    public function cancelRequestCallsDisableWhenNotAlreadyDisabled(): void
    {
        $service = $this->createMock(SegmentDisabledService::class);
        $service->method('isDisabled')->willReturn(false);
        $service->expects($this->once())->method('disable')->with(42);

        $controller = $this->createActionController(segmentDisabledService: $service);

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->response->method('code')->willReturn(200);

        $controller->cancelRequest();
    }

    #[Test]
    public function cancelRequestWithDifferentSegmentId(): void
    {
        $controller = $this->createActionController();

        $this->request->method('param')->willReturnCallback(function ($key) {
            return match ($key) {
                'id_job' => 10,
                'password' => 'pwd123',
                'id_segment' => 55,
                default => null,
            };
        });

        $this->response->method('code')->willReturn(200);
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

        $jobStruct = $this->createStub(JobStruct::class);
        $controller = $this->createControllerWithPartialMock(jobReturn: $jobStruct, segmentReturn: null);

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

        $projectStruct = $this->createStub(ProjectStruct::class);
        $projectStruct->id_team = null;

        $jobStruct = $this->createStub(JobStruct::class);
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

        $teamStruct = $this->createStub(TeamStruct::class);
        $teamStruct->created_by = 999;
        $teamStruct->method('hasUser')->willReturn(false);

        $teamDao = $this->createStub(TeamDao::class);
        $teamDao->method('findById')->willReturn($teamStruct);

        $projectStruct = $this->createStub(ProjectStruct::class);
        $projectStruct->id_team = 1;

        $jobStruct = $this->createStub(JobStruct::class);
        $jobStruct->method('getProject')->willReturn($projectStruct);

        $segmentTranslation = new SegmentTranslationStruct();
        $segmentTranslation->status = 'NEW';

        $user = $this->createStub(UserStruct::class);
        $user->uid = 123;
        $user->email = 'test@example.com';

        $controller = $this->createControllerWithPartialMock(
            jobReturn: $jobStruct,
            segmentReturn: $segmentTranslation,
            user: $user,
            teamDao: $teamDao,
        );

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $controller->cancelRequest();
    }

    #[Test]
    public function performChecksPassesWhenUserIsTeamMemberButNotOwner(): void
    {
        $this->expectException(ConflictError::class);
        $this->expectExceptionMessage('Segment is not in "new" status and cannot be disabled');

        $teamStruct = $this->createStub(TeamStruct::class);
        $teamStruct->created_by = 999;
        $teamStruct->method('hasUser')->willReturn(true);

        $teamDao = $this->createStub(TeamDao::class);
        $teamDao->method('findById')->willReturn($teamStruct);

        $projectStruct = $this->createStub(ProjectStruct::class);
        $projectStruct->id_team = 1;

        $jobStruct = $this->createStub(JobStruct::class);
        $jobStruct->method('getProject')->willReturn($projectStruct);

        $segmentTranslation = new SegmentTranslationStruct();
        $segmentTranslation->status = 'TRANSLATED';

        $user = $this->createStub(UserStruct::class);
        $user->uid = 123;
        $user->email = 'test@example.com';

        $controller = $this->createControllerWithPartialMock(
            jobReturn: $jobStruct,
            segmentReturn: $segmentTranslation,
            user: $user,
            teamDao: $teamDao,
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
        $this->expectException(ConflictError::class);
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
        $this->expectException(ConflictError::class);
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
        $this->expectException(ConflictError::class);
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
        $this->expectException(ConflictError::class);
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
    public function performChecksReturnsEarlyOnRateLimitForIp(): void
    {
        $rateLimitedResponse = $this->createStub(Response::class);
        $rateLimitedResponse->method('code')->willReturn(429);

        $controller = $this->createControllerWithPartialMock(rateLimitResponseIp: $rateLimitedResponse);

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->response->expects($this->never())->method('json');

        $controller->cancelRequest();
    }

    #[Test]
    public function performChecksReturnsEarlyOnRateLimitForEmail(): void
    {
        $rateLimitedResponse = $this->createStub(Response::class);
        $rateLimitedResponse->method('code')->willReturn(429);

        $controller = $this->createControllerWithPartialMock(rateLimitResponseEmail: $rateLimitedResponse);

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->response->expects($this->never())->method('json');

        $controller->cancelRequest();
    }

    #[Test]
    public function performChecksPassesWhenUserIsTeamOwnerAndSegmentIsNew(): void
    {
        $teamStruct = $this->createStub(TeamStruct::class);
        $teamStruct->created_by = 123;

        $teamDao = $this->createStub(TeamDao::class);
        $teamDao->method('findById')->willReturn($teamStruct);

        $projectStruct = $this->createStub(ProjectStruct::class);
        $projectStruct->id_team = 1;

        $jobStruct = $this->createStub(JobStruct::class);
        $jobStruct->method('getProject')->willReturn($projectStruct);

        $segmentTranslation = new SegmentTranslationStruct();
        $segmentTranslation->status = 'NEW';

        $user = $this->createStub(UserStruct::class);
        $user->uid = 123;
        $user->email = 'owner@example.com';

        $controller = $this->createControllerWithPartialMock(
            jobReturn: $jobStruct,
            segmentReturn: $segmentTranslation,
            user: $user,
            teamDao: $teamDao,
        );

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->response->method('code')->willReturn(200);
        $this->response->expects($this->once())
            ->method('json')
            ->with(['id_segment' => 42]);

        $controller->enableRequest();
    }

    #[Test]
    public function performChecksUsesBlankEmailWhenUserEmailIsNull(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Job not found.');

        $user = $this->createStub(UserStruct::class);
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
    public function performChecksCallsCheckAndIncrementTwice(): void
    {
        $user = $this->createStub(UserStruct::class);
        $user->uid = 123;
        $user->email = 'test@example.com';

        $controller = $this->getMockBuilder(CancelRequestController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getJob', 'checkAndIncrementRateLimit', 'getUser'])
            ->getMock();

        $ref = new ReflectionClass(CancelRequestController::class);
        $ref->getProperty('request')->setValue($controller, $this->request);
        $ref->getProperty('response')->setValue($controller, $this->response);
        $ref->getProperty('user')->setValue($controller, $user);

        $segmentTranslationDao = $this->createStub(SegmentTranslationDao::class);
        $segmentTranslationDao->method('findBySegmentAndJob')->willReturn(null);
        $ref->getProperty('segmentTranslationDao')->setValue($controller, $segmentTranslationDao);

        $segmentDisabledService = $this->createStub(SegmentDisabledService::class);
        $ref->getProperty('segmentDisabledService')->setValue($controller, $segmentDisabledService);

        $controller->method('getUser')->willReturn($user);
        $controller->method('getJob')->willReturn(null);
        $controller->method('checkAndIncrementRateLimit')->willReturn(null);

        $controller->expects($this->exactly(2))
            ->method('checkAndIncrementRateLimit');

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->expectException(NotFoundException::class);
        $controller->cancelRequest();
    }

    #[Test]
    public function enableRequestReturnsEarlyWhenRateLimited(): void
    {
        $rateLimitedResponse = $this->createStub(Response::class);
        $rateLimitedResponse->method('code')->willReturn(429);

        $controller = $this->createControllerWithPartialMock(rateLimitResponseIp: $rateLimitedResponse);

        $this->request->method('param')->willReturnMap([
            ['id_job', null, 1],
            ['password', null, 'abc123'],
            ['id_segment', null, 42],
        ]);

        $this->response->expects($this->never())->method('json');

        $controller->enableRequest();
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function buildControllerWithSegmentStatus(string $status): CancelRequestController
    {
        $teamStruct = $this->createStub(TeamStruct::class);
        $teamStruct->created_by = 123;

        $teamDao = $this->createStub(TeamDao::class);
        $teamDao->method('findById')->willReturn($teamStruct);

        $projectStruct = $this->createStub(ProjectStruct::class);
        $projectStruct->id_team = 1;

        $jobStruct = $this->createStub(JobStruct::class);
        $jobStruct->method('getProject')->willReturn($projectStruct);

        $segmentTranslation = new SegmentTranslationStruct();
        $segmentTranslation->status = $status;

        $user = $this->createStub(UserStruct::class);
        $user->uid = 123;
        $user->email = 'test@example.com';

        return $this->createControllerWithPartialMock(
            jobReturn: $jobStruct,
            segmentReturn: $segmentTranslation,
            user: $user,
            teamDao: $teamDao,
        );
    }

    private function createActionController(
        ?SegmentDisabledService $segmentDisabledService = null,
    ): CancelRequestController {
        $teamStruct = $this->createStub(TeamStruct::class);
        $teamStruct->created_by = 123;

        $teamDao = $this->createStub(TeamDao::class);
        $teamDao->method('findById')->willReturn($teamStruct);

        $projectStruct = $this->createStub(ProjectStruct::class);
        $projectStruct->id_team = 1;

        $jobStruct = $this->createStub(JobStruct::class);
        $jobStruct->method('getProject')->willReturn($projectStruct);

        $segmentTranslation = new SegmentTranslationStruct();
        $segmentTranslation->status = TranslationStatus::STATUS_NEW;

        $user = $this->createStub(UserStruct::class);
        $user->uid = 123;
        $user->email = 'test@example.com';

        return $this->createControllerWithPartialMock(
            jobReturn: $jobStruct,
            segmentReturn: $segmentTranslation,
            user: $user,
            segmentDisabledService: $segmentDisabledService,
            teamDao: $teamDao,
        );
    }

    private function createControllerWithPartialMock(
        mixed $jobReturn = 'NOT_SET',
        mixed $segmentReturn = 'NOT_SET',
        ?UserStruct $user = null,
        ?Response $rateLimitResponseIp = null,
        ?Response $rateLimitResponseEmail = null,
        ?SegmentDisabledService $segmentDisabledService = null,
        ?TeamDao $teamDao = null,
    ): CancelRequestController {
        $controller = $this->getMockBuilder(CancelRequestController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getJob', 'checkAndIncrementRateLimit', 'getUser'])
            ->getMock();

        $ref = new ReflectionClass(CancelRequestController::class);

        $ref->getProperty('request')->setValue($controller, $this->request);
        $ref->getProperty('response')->setValue($controller, $this->response);

        if ($user === null) {
            $user = $this->createStub(UserStruct::class);
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

        // Inject SegmentTranslationDao
        $segmentTranslationDao = $this->createStub(SegmentTranslationDao::class);
        if ($segmentReturn === 'NOT_SET') {
            $segmentTranslationDao->method('findBySegmentAndJob')->willReturn(null);
        } else {
            $segmentTranslationDao->method('findBySegmentAndJob')->willReturn($segmentReturn);
        }
        $ref->getProperty('segmentTranslationDao')->setValue($controller, $segmentTranslationDao);

        // Inject SegmentDisabledService
        $ref->getProperty('segmentDisabledService')->setValue(
            $controller,
            $segmentDisabledService ?? $this->createStub(SegmentDisabledService::class)
        );

        // Inject TeamDao
        if ($teamDao !== null) {
            $ref->getProperty('teamDao')->setValue($controller, $teamDao);
        } else {
            $ref->getProperty('teamDao')->setValue($controller, $this->createStub(TeamDao::class));
        }

        // Rate limit mocking — order matches performChecks: [$userIp, $userEmail]
        $callIndex = 0;
        $controller->method('checkAndIncrementRateLimit')
            ->willReturnCallback(function () use (&$callIndex, $rateLimitResponseIp, $rateLimitResponseEmail) {
                $callIndex++;
                if ($callIndex === 1) {
                    return $rateLimitResponseIp;
                }
                if ($callIndex === 2) {
                    return $rateLimitResponseEmail;
                }
                return null;
            });

        return $controller;
    }
}
