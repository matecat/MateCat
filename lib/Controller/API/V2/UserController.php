<?php

namespace Controller\API\V2;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\Abstracts\Authentication\AuthenticationHelper;
use Controller\API\Commons\Validators\JSONRequestValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Klein\Exceptions\LockedResponseException;
use Klein\Exceptions\ResponseAlreadySentException;
use Model\Users\MetadataDao;
use Model\Users\UserDao;
use TypeError;
use Utils\Tools\CatUtils;

class UserController extends AbstractStatefulKleinController
{
    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
        $this->appendValidator(new JSONRequestValidator($this));
    }

    /**
     * Edit the user profile
     *
     * @throws LockedResponseException
     * @throws ResponseAlreadySentException
     * @throws TypeError
     */
    public function edit(): void
    {
        $json = $this->request->body();
        $json = json_decode($json ?? '', true);

        $data = filter_var_array(
            $json,
            [
                'first_name' => [
                    'filter' => FILTER_CALLBACK,
                    'options' => function ($firstName) {
                        return CatUtils::stripMaliciousContentFromAName($firstName);
                    }
                ],
                'last_name' => [
                    'filter' => FILTER_CALLBACK,
                    'options' => function ($lastName) {
                        return CatUtils::stripMaliciousContentFromAName($lastName);
                    }
                ],
            ]
        );


        try {
            if (empty($data['first_name'])) {
                throw new InvalidArgumentException('First name must contain at least one letter', 400);
            }

            if (empty($data['last_name'])) {
                throw new InvalidArgumentException('Last name must contain at least one letter', 400);
            }

            $user = $this->user;
            $user->first_name = $data['first_name'];
            $user->last_name = $data['last_name'];
            $uid = $user->uid ?? throw new Exception('User not authenticated');

            $userDao = new UserDao($this->getDatabase());
            $userDao->updateUser($user);
            $userDao->destroyCacheByUid($uid);

            AuthenticationHelper::fromRequest($_SESSION, $this->getDatabase())->refreshSession();

            $this->response->json([
                'uid' => $user->uid,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'create_date' => $user->create_date,
            ]);
        } catch (Exception $exception) {
            $this->response->code($exception->getCode());

            $this->response->json([
                'error' => $exception->getMessage()
            ]);
        }
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     * @throws LockedResponseException
     * @throws ResponseAlreadySentException
     */
    public function setMetadata(): void
    {
        $json = $this->request->body();

        $filters = [
            'key' => FILTER_SANITIZE_SPECIAL_CHARS,
            'value' => FILTER_SANITIZE_SPECIAL_CHARS,
        ];

        $options = [
            'key' => [
                'flags' => FILTER_NULL_ON_FAILURE
            ],
            'value' => [
                'flags' => FILTER_NULL_ON_FAILURE
            ],
        ];

        $json = json_decode($json ?? '', true);

        $filtered = [];
        foreach ((array)$json as $key => $value) {
            if (is_array($value)) {
                $filtered[$key] = filter_var($value, FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY);
            } else {
                $filtered[$key] = filter_var($value, $filters[$key], $options[$key]);
            }
        }

        if (!isset($filtered['key']) || !is_string($filtered['key'])) {
            throw new InvalidArgumentException('`key` required', 400);
        }

        if (!isset($filtered['value']) ||
            (!is_string($filtered['value']) && !is_array($filtered['value']))
        ) {
            throw new InvalidArgumentException('`value` required', 400);
        }

        $uid = $this->user->uid ?? throw new InvalidArgumentException('User not authenticated', 403);

        try {
            $userMetaDao = new MetadataDao($this->getDatabase());
            $metadata = $userMetaDao->set(
                $uid,
                $filtered['key'],
                $filtered['value']
            );

            AuthenticationHelper::fromRequest($_SESSION, $this->getDatabase())->refreshSession();

            $this->response->json($metadata);
        } catch (Exception $exception) {
            $this->response->code($exception->getCode() > 0 ? $exception->getCode() : 500);

            $this->response->json([
                'error' => $exception->getMessage()
            ]);
        }
    }
}
