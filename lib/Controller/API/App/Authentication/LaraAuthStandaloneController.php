<?php

namespace Controller\API\App\Authentication;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\API\App\Authentication\Traits\LaraAuthTrait;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Services\RateLimiterInterface;
use Controller\Services\RateLimiterService;
use DomainException;
use Exception;
use Klein\App;
use Klein\Request;
use Klein\Response;
use Klein\ServiceProvider;
use Lara\LaraException;
use Model\DataAccess\Database;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use Utils\Engines\Lara;
use Utils\Logger\LoggerFactory;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\ContextList;

class LaraAuthStandaloneController extends AbstractStatefulKleinController
{
    use LaraAuthTrait;

    private readonly RateLimiterInterface $rateLimiter;

    /**
     * @param Request              $request
     * @param Response             $response
     * @param ServiceProvider|null $service
     * @param App|null             $app
     * @param RateLimiterInterface|null $rateLimiter
     *
     * @throws Exception
     */
    public function __construct(
        Request $request,
        Response $response,
        ?ServiceProvider $service = null,
        ?App $app = null,
        ?RateLimiterInterface $rateLimiter = null,
    ) {
        parent::__construct($request, $response, $service, $app);
        $contextList = ContextList::get(AppConfig::$TASK_RUNNER_CONFIG['context_definitions']);
        $loggerName = $contextList->list['CONTRIBUTION_GET']->loggerName;
        $this->logger = LoggerFactory::getLogger($loggerName, $loggerName);
        $this->rateLimiter = $rateLimiter ?? new RateLimiterService();
    }

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    protected function getRateLimiter(): RateLimiterInterface
    {
        return $this->rateLimiter;
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
        $engineDAO = new EngineDAO(Database::obtain());
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
}

