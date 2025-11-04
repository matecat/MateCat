<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 30/01/2017
 * Time: 18:09
 */

namespace Controller\API\V2;


use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\Traits\RateLimiterTrait;
use Exception;
use Model\ApiKeys\ApiKeyDao;
use Utils\Tools\Utils;

class KeyCheckController extends KleinController
{

    use RateLimiterTrait;

    /**
     * @throws AuthenticationError
     * @throws Exception
     */
    public function ping(): void
    {
        $checkRateLimitEmail = $this->checkRateLimitResponse($this->response, $this->getUser()->email ?? "BLANK_EMAIL", '/api/v2/user/ping', 3);
        $checkRateLimitIp    = $this->checkRateLimitResponse($this->response, Utils::getRealIpAddr() ?? "127.0.0.1", '/api/v2/user/ping', 3);

        if ($checkRateLimitEmail) {
            $this->response = $checkRateLimitEmail;

            return;
        }

        if ($checkRateLimitIp) {
            $this->response = $checkRateLimitIp;

            return;
        }

        $this->incrementRateLimitCounter($this->getUser()->email ?? "BLANK_EMAIL", '/api/v2/user/ping');
        $this->incrementRateLimitCounter(Utils::getRealIpAddr() ?? "127.0.0.1", '/api/v2/user/ping');

        if (!$this->getApiRecord()) {
            throw new AuthenticationError();
        }

        $this->response->code(200);
    }

    /**
     * @throws NotFoundException
     * @throws AuthenticationError
     * @throws Exception
     */
    public function getUID(): void
    {
        $checkRateLimitEmail = $this->checkRateLimitResponse($this->response, $this->getUser()->email ?? "BLANK_EMAIL", '/api/v2/user/[:user_api_key]', 3);
        $checkRateLimitIp    = $this->checkRateLimitResponse($this->response, Utils::getRealIpAddr() ?? "127.0.0.1", '/api/v2/user/[:user_api_key]', 3);

        if ($checkRateLimitEmail) {
            $this->response = $checkRateLimitEmail;

            return;
        }

        if ($checkRateLimitIp) {
            $this->response = $checkRateLimitIp;

            return;
        }

        if (!$this->getApiRecord()) {
            $this->incrementRateLimitCounter($this->getUser()->email ?? "BLANK_EMAIL", '/api/v2/user/[:user_api_key]');
            $this->incrementRateLimitCounter(Utils::getRealIpAddr() ?? "127.0.0.1", '/api/v2/user/[:user_api_key]');
            throw new AuthenticationError('Unauthorized', 401);
        }

        [$user_api_key, $user_api_secret] = explode('-', $this->params[ 'user_api_key' ]);

        if ($user_api_key && $user_api_secret) {
            $api_record = ApiKeyDao::findByKey($user_api_key);

            if ($api_record && $api_record->validSecret($user_api_secret)) {
                $userJson = ['user' => ['uid' => $api_record->uid]];
                $this->response->json($userJson);

                return;
            }
        }

        $this->incrementRateLimitCounter($this->getUser()->email ?? "BLANK_EMAIL", '/api/v2/user/[:user_api_key]');
        $this->incrementRateLimitCounter(Utils::getRealIpAddr() ?? "127.0.0.1", '/api/v2/user/[:user_api_key]');

        throw new NotFoundException("User not found.", 404);
    }

}