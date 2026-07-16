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
use Exception;
use Klein\App;
use RuntimeException;
use Klein\Request;
use Klein\Response;
use Klein\ServiceProvider;
use Lara\LaraException;
use Model\Jobs\JobStruct;
use Utils\Engines\EnginesFactory;
use Utils\Engines\Lara;
use Utils\Engines\Lara\Headers;
use Utils\Logger\LoggerFactory;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\ContextList;
use Utils\TmKeyManagement\TmKeyManager;
use Utils\TmKeyManagement\TmKeyStruct;
use TypeError;
use Utils\Tools\Utils;

class LaraAuthController extends AbstractStatefulKleinController
{

    use LaraAuthTrait;

    private JobStruct $chunk;

    /**
     * @param Request $request
     * @param Response $response
     * @param ServiceProvider|null $service
     * @param App|null $app
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
     * @throws TypeError
     */
    protected function initLogger(): void
    {
        $contextList = ContextList::get(AppConfig::$TASK_RUNNER_CONFIG['context_definitions']);
        $loggerName = $contextList->list['CONTRIBUTION_GET']->loggerName;
        $this->logger = LoggerFactory::getLogger($loggerName, $loggerName);
    }

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));

        $chunkValidator = new ChunkPasswordValidator($this, ttl: 3600);
        $this->appendValidator(
            $chunkValidator
                ->onSuccess(fn() => $this->onChunkValidated($chunkValidator))
                ->onSuccess(fn() => $this->enforceReasoningOwner($chunkValidator))
        );
    }

    /**
     * Stores the chunk resolved by the ChunkPasswordValidator on the controller.
     *
     * Exposed as a protected seam for unit testing the post-validation callback.
     *
     * @param ChunkPasswordValidator $chunkValidator
     *
     * @return void
     * @throws RuntimeException
     */
    protected function onChunkValidated(ChunkPasswordValidator $chunkValidator): void
    {
        $this->chunk = $chunkValidator->getChunk();
    }

    /**
     * When the `reasoning` parameter is truthy, ensures the requesting user is the
     * internal owner of the chunk by running IsOwnerInternalUserValidator.
     *
     * Exposed as a protected seam for unit testing the post-validation callback.
     *
     * @param ChunkPasswordValidator $chunkValidator
     *
     * @return void
     * @throws Exception
     */
    protected function enforceReasoningOwner(ChunkPasswordValidator $chunkValidator): void
    {
        $reasoning = (bool)filter_var($this->getParams()['reasoning'] ?? null, FILTER_VALIDATE_BOOLEAN);
        if ($reasoning) {
            $this->buildOwnerValidator($chunkValidator->getChunk())->validate();
        }
    }

    /**
     * Builds the IsOwnerInternalUserValidator for the given chunk.
     *
     * Exposed as a protected seam so tests can substitute the validator without
     * touching the DB-backed dependencies pulled in by IsOwnerInternalUserValidator.
     *
     * @param JobStruct $chunk
     *
     * @return IsOwnerInternalUserValidator
     */
    protected function buildOwnerValidator(JobStruct $chunk): IsOwnerInternalUserValidator
    {
        return new IsOwnerInternalUserValidator($this, $chunk);
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
        if ($this->checkRateLimits()) {
            return;
        }


        // Parse + filter the chunk TM keys, keeping only "owner" keys with read ("r") permission.
        $tm_keys = TmKeyManager::getOwnerKeys([$this->chunk->tm_keys ?? '[]'], 'r');

        // Extract raw key strings, filter nulls, join into a comma-separated list.
        // performLaraAuth() handles remapping to Lara external memory IDs internally.
        $tmKeysList = implode(
            ",",
            array_filter(
                array_map(fn(TmKeyStruct $tm_key): ?string => $tm_key->key, $tm_keys),
                fn(?string $key): bool => $key !== null
            )
        );

        $this->performLaraAuth($this->chunk->id_mt_engine, $tmKeysList);
    }

}
