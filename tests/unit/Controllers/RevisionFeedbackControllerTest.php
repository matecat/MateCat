<?php

namespace unit\Controllers;

use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\V3\RevisionFeedbackController;
use Klein\Request;
use Klein\Response;
use Model\Jobs\JobStruct;
use Model\ReviseFeedback\FeedbackDAO;
use Model\ReviseFeedback\FeedbackStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use TestHelpers\AbstractTest;

class TestableRevisionFeedbackController extends RevisionFeedbackController
{
    public function __construct()
    {
    }

    protected function afterConstruct(): void
    {
    }

    public ?FeedbackDAO $injectedFeedbackDao = null;

    protected function createFeedbackDao(): FeedbackDAO
    {
        return $this->injectedFeedbackDao ?? new FeedbackDAO();
    }

}

#[AllowMockObjectsWithoutExpectations]
class RevisionFeedbackControllerTest extends AbstractTest
{
    private ReflectionClass $reflector;
    private TestableRevisionFeedbackController $controller;
    /** @var Request&MockObject */
    private Request&MockObject $requestStub;
    /** @var Response&MockObject */
    private Response&MockObject $responseMock;

    /** @throws ReflectionException */
    protected function setUp(): void
    {
        parent::setUp();

        $this->reflector = new ReflectionClass(TestableRevisionFeedbackController::class);
        $this->controller = $this->reflector->newInstanceWithoutConstructor();

        $this->requestStub = $this->createMock(Request::class);
        $this->responseMock = $this->createMock(Response::class);
        $this->responseMock->method('json')->willReturnSelf();

        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);

        $chunk = $this->createStub(JobStruct::class);
        $chunk->method('isDeleted')->willReturn(false);
        $this->reflector->getProperty('chunk')->setValue($this->controller, $chunk);
    }

    private function setRequestParams(): void
    {
        $this->requestStub->method('param')->willReturnCallback(
            static fn(string $key) => match ($key) {
                'id_job' => 123,
                'password' => 'abc',
                'revision_number' => 1,
                'feedback' => 'Good translation',
                default => null,
            }
        );
    }

    #[Test]
    public function afterConstructAppendsLoginAndChunkPasswordValidators(): void
    {
        $realReflector = new ReflectionClass(RevisionFeedbackController::class);
        /** @var RevisionFeedbackController $realController */
        $realController = $realReflector->newInstanceWithoutConstructor();

        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

        $realReflector->getProperty('request')->setValue($realController, $request);
        $realReflector->getProperty('response')->setValue($realController, $response);
        $realReflector->getProperty('params')->setValue($realController, [
            'id_job' => 123,
            'password' => 'abc',
            'revision_number' => 1,
        ]);

        $realReflector->getMethod('afterConstruct')->invoke($realController);

        /** @var list<mixed> $validators */
        $validators = $realReflector->getProperty('validators')->getValue($realController);
        $this->assertCount(2, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
        $this->assertInstanceOf(ChunkPasswordValidator::class, $validators[1]);

        /** @var ChunkPasswordValidator $chunkValidator */
        $chunkValidator = $validators[1];
        $validatorReflector = new ReflectionClass($chunkValidator);

        $expectedChunk = $this->createStub(JobStruct::class);
        $validatorReflector->getProperty('chunk')->setValue($chunkValidator, $expectedChunk);

        $callbacks = $validatorReflector->getParentClass()->getProperty('_validationCallbacks')->getValue($chunkValidator);
        $this->assertNotEmpty($callbacks);
        $callbacks[0]();

        $assignedChunk = $realReflector->getProperty('chunk')->getValue($realController);
        $this->assertSame($expectedChunk, $assignedChunk);
    }

    #[Test]
    public function createFeedbackDaoReturnsConcreteDaoWhenNotInjected(): void
    {
        $realReflector = new ReflectionClass(RevisionFeedbackController::class);
        /** @var RevisionFeedbackController $realController */
        $realController = $realReflector->newInstanceWithoutConstructor();

        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

        $realReflector->getProperty('request')->setValue($realController, $request);
        $realReflector->getProperty('response')->setValue($realController, $response);

        $method = $realReflector->getMethod('createFeedbackDao');
        /** @var FeedbackDAO $dao */
        $dao = $method->invoke($realController);

        $this->assertInstanceOf(FeedbackDAO::class, $dao);
    }

    #[Test]
    public function feedbackReturnsOkWhenInsertOrUpdateAffectsRows(): void
    {
        $this->setRequestParams();

        $mockDao = $this->createMock(FeedbackDAO::class);
        $mockDao
            ->expects($this->once())
            ->method('insertOrUpdate')
            ->with($this->callback(function (FeedbackStruct $struct): bool {
                $this->assertSame(123, $struct->id_job);
                $this->assertSame('abc', $struct->password);
                $this->assertSame(1, $struct->revision_number);
                $this->assertSame('Good translation', $struct->feedback);

                return true;
            }))
            ->willReturn(1);

        $this->controller->injectedFeedbackDao = $mockDao;

        $this->responseMock
            ->expects($this->once())
            ->method('json')
            ->with(['status' => 'ok'])
            ->willReturnSelf();

        $this->controller->feedback();
    }

    #[Test]
    public function feedbackReturnsKoWhenInsertOrUpdateDoesNotAffectRows(): void
    {
        $this->setRequestParams();

        $mockDao = $this->createMock(FeedbackDAO::class);
        $mockDao
            ->expects($this->once())
            ->method('insertOrUpdate')
            ->willReturn(0);

        $this->controller->injectedFeedbackDao = $mockDao;

        $this->responseMock
            ->expects($this->once())
            ->method('json')
            ->with(['status' => 'ko'])
            ->willReturnSelf();

        $this->controller->feedback();
    }
}
