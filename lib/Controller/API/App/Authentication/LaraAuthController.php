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
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\IsOwnerInternalUserValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\RateLimiterTrait;
use DomainException;
use Exception;
use Klein\Response;
use Lara\LaraException;
use Model\Jobs\JobStruct;
use Model\TmKeyManagement\MemoryKeyStruct;
use Utils\Engines\EnginesFactory;
use Utils\Engines\Lara;
use Utils\TmKeyManagement\TmKeyManager;
use Utils\Tools\Utils;

class LaraAuthController extends AbstractStatefulKleinController
{

    use RateLimiterTrait;

    private JobStruct $chunk;

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));

        $chunkValidator = new ChunkPasswordValidator($this);
        $this->appendValidator(
            $chunkValidator->onSuccess(
                function () use ($chunkValidator) {
                    $this->chunk = $chunkValidator->getChunk();
                }
            )->onSuccess(
                fn() => (new IsOwnerInternalUserValidator($this, $this->chunk))->validate()
            )
        );
    }

    /**
     * Handles authentication by validating rate limits, interacting with the Lara engine,
     * and generating an authentication token for the client. The method also manages rate
     * limit counters and ensures that the necessary headers are sent to the Lara engine for
     * processing and token generation.
     *
     * @return void
     * @throws LaraException
     * @throws Exception
     */
    public function auth(): void
    {
        $email = $this->getUser()->email ?? 'BLANK_EMAIL';
        $ip = Utils::getRealIpAddr() ?? '127.0.0.1';
        $rateLimitKey = '/api/app/lara/token';

        // Check the rate limit for this endpoint using the user's email as the identifier (max 30 attempts in the current window).
        $checkRateLimitResponse = $this->checkRateLimitResponse($this->response, $email, $rateLimitKey, 30);

        // Also, check the rate limit for the same endpoint using the client's IP as the identifier (max 30 attempts in the current window).
        $checkRateLimitIp = $this->checkRateLimitResponse($this->response, $ip, $rateLimitKey, 30);

        // If the email-based check returned a Response, it means the limit was exceeded:
        // replace the controller response (typically set to HTTP 429 + Retry-After) and stop processing.
        if ($checkRateLimitResponse instanceof Response) {
            $this->response = $checkRateLimitResponse;
            return;
        }

        // If the IP-based check returned a Response, do the same: send the rate-limit response and stop processing.
        if ($checkRateLimitIp instanceof Response) {
            $this->response = $checkRateLimitIp;
            return;
        }

        try {
            try {
                $laraEngine = EnginesFactory::getInstance($this->chunk->id_mt_engine, Lara::class);
            } catch (Exception $e) {
                throw new DomainException("Job MT engine is not a Lara engine", $e->getCode(), $e);
            }

            // Grab the engine’s internal HTTP client (the one that actually performs API requests).
            $laraClient = $laraEngine->getInternalClient();

            // Parse + filter the chunk TM keys, keeping only “owner” keys with read ("r") permission.
            // The result is an array of TmKeyStruct objects.
            $tm_keys = TmKeyManager::getOwnerKeys([$this->chunk->tm_keys ?? '[]'], 'r');

            // Extract raw key strings, remap them to Lara external memory IDs (prefix "ext_my_"),
            // then join into a comma-separated list for a single header value.
            $tm_keys = implode(
                ",",
                $laraEngine->reMapKeyList(
                    array_map(function ($tm_key) {
                        // expected element type; we only use its ->key value
                        /** @var $tm_key MemoryKeyStruct */
                        return $tm_key->key;
                    }, $tm_keys)
                )
            );

            // Send the selected memory IDs to Lara via a custom request header.
            $laraClient->setExtraHeader('x-memory-ids', $tm_keys);

            // Authenticate the client (likely producing an auth token for later requests).
            $token = $laraClient->authenticate();
            $this->response->code(200);
            $this->response->json(['token' => $token]);
        } finally {
            // Always increment after a non-rate-limited attempt (success or failure)
            $this->incrementRateLimitCounter($email, $rateLimitKey);
            $this->incrementRateLimitCounter($ip, $rateLimitKey);
        }
    }

}