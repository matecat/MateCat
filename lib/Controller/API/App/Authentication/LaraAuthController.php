<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 11/12/25
 * Time: 17:39
 *
 */

namespace Controller\API\App\Authentication;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\API\App\Authentication\Traits\LaraAuthTrait;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\IsOwnerInternalUserValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\RateLimiterTrait;
use DomainException;
use Exception;
use Klein\App;
use Klein\Request;
use Klein\Response;
use Klein\ServiceProvider;
use Lara\LaraException;
use Model\Jobs\JobStruct;
use Model\TmKeyManagement\MemoryKeyStruct;
use Utils\Engines\EnginesFactory;
use Utils\Engines\Lara;
use Utils\Logger\LoggerFactory;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\ContextList;
use Utils\TmKeyManagement\TmKeyManager;

class LaraAuthController extends AbstractStatefulKleinController
{

    use RateLimiterTrait;
    use LaraAuthTrait;

    private JobStruct $chunk;

    /**
     * @param Request $request
     * @param Response $response
     * @param ServiceProvider|null $service
     * @param App|null $app
     *
     * @throws Exception
     */
    public function __construct(Request $request, Response $response, ?ServiceProvider $service = null, ?App $app = null)
    {
        parent::__construct($request, $response, $service, $app);
        $contextList = ContextList::get(AppConfig::$TASK_RUNNER_CONFIG['context_definitions']);
        $loggerName = $contextList->list['CONTRIBUTION_GET']->loggerName;
        $this->logger = LoggerFactory::getLogger($loggerName,$loggerName);
    }

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));

        $chunkValidator = new ChunkPasswordValidator($this, ttl: 3600);
        $this->appendValidator(
            $chunkValidator->onSuccess(
                function () use ($chunkValidator) {
                    $this->chunk = $chunkValidator->getChunk();
                }
            )->onSuccess(
                function () use ($chunkValidator) {
                    $reasoning = (bool)filter_var($this->getParams()['reasoning'], FILTER_VALIDATE_BOOLEAN);
                    if($reasoning){
                        (new IsOwnerInternalUserValidator($this, $chunkValidator->getChunk()))->validate();
                    }                
                }
            )
        );
    }

    /**
     * Handles authentication by validating rate limits, interacting with the Lara engine,
     * and generating an authentication token for the client.
     *
     * @return void
     * @throws LaraException
     * @throws Exception
     */
    public function auth(): void
    {
        try {
            EnginesFactory::getInstance($this->chunk->id_mt_engine, Lara::class);
        } catch (Exception $e) {
            throw new DomainException("Job MT engine is not a Lara engine", $e->getCode(), $e);
        }

        // Parse + filter the chunk TM keys, keeping only "owner" keys with read ("r") permission.
        $tm_keys = TmKeyManager::getOwnerKeys([$this->chunk->tm_keys ?? '[]'], 'r');

        // Extract raw key strings
        $tmKeysList = implode(
            ',',
            array_map(function ($tm_key) {
                /** @var $tm_key MemoryKeyStruct */
                return $tm_key->key;
            }, $tm_keys)
        );

        $this->performLaraAuth($this->chunk->id_mt_engine, $tmKeysList);
    }

}
