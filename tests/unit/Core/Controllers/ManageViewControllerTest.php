<?php

namespace Matecat\Core\Controllers;

/*
 * Reserved ID block (Playbook §4): base = 9065000
 *   user = 9065006. No project/job rows are required: ManageController::renderView()
 *   only reads $this->user->uid and enqueues an ActivityLogStruct via
 *   Activity::save -> WorkerClient::enqueue (ActiveMQ); it never touches the
 *   database directly.
 * Clean ONLY by reserved id; never by shared keys.
 */

use Controller\API\Commons\ViewValidators\ViewLoginRedirectValidator;
use Controller\Exceptions\RenderTerminatedException;
use Controller\Views\ManageController;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\ActivityLog\ActivityLogStruct;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Stomp\Transport\Message;
use Utils\ActiveMQ\AMQHandler;
use Utils\ActiveMQ\WorkerClient;
use Utils\Logger\MatecatLogger;
use Utils\Templating\PHPTalBoolean;

class TestableManageViewController extends ManageController
{
    public function __construct()
    {
    }

    public string $lastTemplate = '';
    /** @var array<string, mixed> */
    public array $lastViewData = [];
    public int $lastViewCode = 200;

    public function setView(string $template_name, array $params = [], int $code = 200): void
    {
        $this->lastTemplate = $template_name;
        $this->lastViewData = $params;
        $this->lastViewCode = $code;
    }

    /**
     * @throws RenderTerminatedException
     */
    public function render(?int $code = null): never
    {
        throw new RenderTerminatedException();
    }
}

#[AllowMockObjectsWithoutExpectations]
class ManageViewControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9065000;

    /** @var ReflectionClass<TestableManageViewController> */
    private ReflectionClass $reflector;
    private TestableManageViewController $controller;

    /**
     * @throws \Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanTestData();
        $this->seedUser(self::BASE);

        $this->reflector = new ReflectionClass(TestableManageViewController::class);
        $this->controller = $this->reflector->newInstanceWithoutConstructor();

        $this->reflector->getProperty('request')->setValue($this->controller, $this->createMock(Request::class));
        $this->reflector->getProperty('response')->setValue($this->controller, $this->createMock(Response::class));
        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createMock(MatecatLogger::class));
        $this->reflector->getProperty('featureSet')->setValue($this->controller, new FeatureSet($this->createStub(IDatabase::class)));
        $this->reflector->getProperty('database')->setValue($this->controller, obtainTestDatabase());

        $user             = new UserStruct();
        $user->uid        = $this->userId(self::BASE);
        $user->email      = $this->ownerEmail(self::BASE);
        $user->first_name = 'Ctrl';
        $user->last_name  = 'Tester';
        $this->reflector->getProperty('user')->setValue($this->controller, $user);
    }

    /**
     * @throws \Throwable
     */
    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

    private function cleanTestData(): void
    {
        $this->cleanFragments(self::BASE);
    }

    // --- registerValidators (production hook) ---

    /**
     * @throws \Throwable
     */
    #[Test]
    public function registerValidatorsAppendsViewLoginRedirectValidator(): void
    {
        $realReflector = new ReflectionClass(ManageController::class);
        /** @var ManageController $realController */
        $realController = $realReflector->newInstanceWithoutConstructor();

        $realReflector->getProperty('request')->setValue($realController, $this->createMock(Request::class));
        $realReflector->getProperty('response')->setValue($realController, $this->createMock(Response::class));

        $realReflector->getMethod('registerValidators')->invoke($realController);

        /** @var list<mixed> $validators */
        $validators = $realReflector->getProperty('validators')->getValue($realController);
        $this->assertCount(1, $validators);
        $this->assertInstanceOf(ViewLoginRedirectValidator::class, $validators[0]);
    }

    // --- renderView ---

    /**
     * @throws \Throwable
     */
    #[Test]
    public function renderViewSetsManageTemplateAndEnqueuesActivityLog(): void
    {
        $savedHandler = WorkerClient::$_HANDLER;
        $savedQueues  = WorkerClient::$_QUEUES;

        $captured    = null;
        $handlerMock = $this->createMock(AMQHandler::class);
        $handlerMock->expects($this->once())
            ->method('publishToQueues')
            ->with(
                $this->anything(),
                $this->callback(function (Message $message) use (&$captured): bool {
                    $captured = (string)$message->getBody();
                    return true;
                })
            );

        try {
            WorkerClient::init($handlerMock);

            try {
                $this->controller->renderView();
                $this->fail('Expected RenderTerminatedException');
            } catch (RenderTerminatedException) {
                $this->assertSame('manage.html', $this->controller->lastTemplate);
                $this->assertSame('//signin.translated.net/', $this->controller->lastViewData['outsource_service_login']);
                $this->assertInstanceOf(PHPTalBoolean::class, $this->controller->lastViewData['split_enabled']);
                $this->assertSame('true', (string)$this->controller->lastViewData['split_enabled']);
                $this->assertInstanceOf(PHPTalBoolean::class, $this->controller->lastViewData['enable_outsource']);
                $this->assertSame('true', (string)$this->controller->lastViewData['enable_outsource']);
            }

            $this->assertIsString($captured);
            $this->assertStringContainsString('"action":' . ActivityLogStruct::ACCESS_MANAGE_PAGE, $captured);
            $this->assertStringContainsString('"uid":' . $this->userId(self::BASE), $captured);
        } finally {
            WorkerClient::$_HANDLER = $savedHandler;
            WorkerClient::$_QUEUES  = $savedQueues;
        }
    }
}
