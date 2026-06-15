<?php

namespace Controller\API\App\Authentication;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\API\App\Authentication\Traits\LaraAuthTrait;
use Controller\API\Commons\Validators\LoginValidator;
use DomainException;
use Exception;
use Klein\App;
use Klein\Request;
use Klein\Response;
use Klein\ServiceProvider;
use Lara\LaraException;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use Utils\Engines\Lara;
use Utils\Logger\LoggerFactory;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\ContextList;

class LaraAuthStandaloneController extends AbstractStatefulKleinController
{
    use LaraAuthTrait;

    /**
     * @param Request              $request
     * @param Response             $response
     * @param ServiceProvider|null $service
     * @param App|null             $app
     *
     * @throws Exception
     * @throws \TypeError
     */
    public function __construct(
        Request $request,
        Response $response,
        ?ServiceProvider $service = null,
        ?App $app = null
    ) {
        parent::__construct($request, $response, $service, $app);
        $this->initLogger();
    }

    /**
     * Initializes the logger from the task-runner context definitions.
     *
     * Exposed as a protected seam so the wiring can be unit-tested without
     * invoking the heavy parent constructor (session, validators, DB).
     *
     * @return void
     * @throws Exception
     * @throws \TypeError
     */
    protected function initLogger(): void
    {
        $contextList = ContextList::get(AppConfig::$TASK_RUNNER_CONFIG['context_definitions']);
        $loggerName = $contextList->list['CONTRIBUTION_GET']->loggerName;
        $this->logger = LoggerFactory::getLogger($loggerName, $loggerName);
    }

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * Handles Lara authentication without a job context.
     * Resolves the first active Lara engine from the database and authenticates with empty TM keys.
     *
     * @return void
     * @throws LaraException
     * @throws Exception
     */
    public function auth(): void
    {
        if ($this->checkRateLimits()) {
            return;
        }

        $engineId = $this->resolveActiveLaraEngineId();
        $this->performLaraAuth($engineId, '');
    }

    /**
     * Finds the first active Lara engine in the database.
     *
     * @return int
     * @throws Exception
     */
    protected function resolveActiveLaraEngineId(): int
    {
        $engineDAO = $this->getEngineDAO();
        $engineStruct = EngineStruct::getStruct();
        $engineStruct->active = true;
        $engineStruct->class_load = Lara::class;
        $engineStruct->uid = (int)$this->user->uid;

        $engines = $engineDAO->setCacheTTL(60 * 5)->read($engineStruct);

        if (empty($engines)) {
            throw new DomainException('No active Lara engine found');
        }

        return (int)$engines[0]->id;
    }

    /**
     * Returns the EngineDAO instance used by this controller.
     *
     * Exposed as a protected seam so tests can override the DAO without
     * touching the database connection directly.
     *
     * @return EngineDAO
     * @throws Exception
     */
    protected function getEngineDAO(): EngineDAO
    {
        return new EngineDAO($this->db());
    }
}

