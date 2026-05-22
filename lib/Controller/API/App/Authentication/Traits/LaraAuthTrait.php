<?php

namespace Controller\API\App\Authentication\Traits;

use Exception;
use Klein\Response;
use Lara\LaraException;
use Utils\Engines\EnginesFactory;
use Utils\Engines\Lara;
use Utils\Engines\Lara\Headers;
use Utils\Registry\AppConfig;
use Utils\Tools\Utils;

/**
 * Shared Lara authentication logic.
 *
 * Classes using this trait MUST provide:
 *  - RateLimiterTrait (checkAndIncrementRateLimit)
 *  - $this->response (Klein\Response)
 *  - $this->logger (Psr\Log or compatible)
 *  - $this->getUser() returning a user object with ->email
 *
 * @method object getUser()
 * @method Response|null checkAndIncrementRateLimit(Response $response, string $identifier, string $route, int $maxRetries = 10)
 *
 * @property Response $response
 * @property object $logger
 */
trait LaraAuthTrait
{
    /**
     * Checks and enforces rate limits based on email and IP address.
     *
     * This method utilizes pre-defined rate limit rules to track and
     * restrict the number of requests that can be made by a specific email
     * or IP address within a set time period. If rate limits are exceeded,
     * appropriate responses are set to prevent further action.
     *
     * @return void
     */
    protected function checkRateLimits(): void
    {
        $email = $this->getUser()->email ?? 'BLANK_EMAIL';
        $ip = Utils::getRealIpAddr() ?? '127.0.0.1';
        $rateLimitKey = '/api/app/lara/token';

        $rateLimitEmailResponse = $this->checkAndIncrementRateLimit($this->response, $email, $rateLimitKey, 30);
        $rateLimitIpResponse = $this->checkAndIncrementRateLimit($this->response, $ip, $rateLimitKey, 30);

        if ($rateLimitEmailResponse instanceof Response) {
            $this->response = $rateLimitEmailResponse;
        }

        if ($rateLimitIpResponse instanceof Response) {
            $this->response = $rateLimitIpResponse;
        }
    }

    /**
     * Performs Lara authentication: rate-limiting, engine lookup, TM key header injection, and token generation.
     *
     * @param int    $engineId The Lara engine ID to authenticate against.
     * @param string $tmKeys   Comma-separated TM key IDs (can be empty).
     *
     * @return void
     * @throws LaraException
     * @throws Exception
     */
    protected function performLaraAuth(int $engineId, string $tmKeys): void
    {
        $email = $this->getUser()->email ?? 'BLANK_EMAIL';
        $ip = Utils::getRealIpAddr() ?? '127.0.0.1';
        $rateLimitKey = '/api/app/lara/token';

        $rateLimitEmailResponse = $this->checkAndIncrementRateLimit($this->response, $email, $rateLimitKey, 30);
        $rateLimitIpResponse = $this->checkAndIncrementRateLimit($this->response, $ip, $rateLimitKey, 30);

        if ($rateLimitEmailResponse instanceof Response) {
            $this->response = $rateLimitEmailResponse;
            return;
        }

        if ($rateLimitIpResponse instanceof Response) {
            $this->response = $rateLimitIpResponse;
            return;
        }

        $laraEngine = EnginesFactory::getInstance($engineId, Lara::class);
        $laraClient = $laraEngine->getInternalClient();

        if ($tmKeys !== '') {
            $remappedKeys = $laraEngine->reMapKeyList(explode(',', $tmKeys));
            $tmKeysHeader = implode(',', $remappedKeys);
            $laraClient->setExtraHeader(Headers::LARA_MEMORIES_IDS, $tmKeysHeader);
        } else {
            $tmKeysHeader = '';
        }

        $token = $laraClient->authenticate();

        $this->logger->debug([
            'LARA AUTH REQUEST' => 'from browser',
            'headers' => [
                Headers::LARA_PRE_SHARED_KEY_HEADER => substr(AppConfig::$LARA_PRE_SHARED_KEY_HEADER, 0, 16) . '...',
                Headers::LARA_MEMORIES_IDS => $tmKeysHeader,
            ],
            'token' => $token,
        ]);

        $this->response->code(200);
        $this->response->json(['token' => $token]);
    }
}

