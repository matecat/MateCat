<?php

namespace Controller\API\V2;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\Abstracts\Authentication\AuthenticationHelper;
use Controller\API\Commons\Validators\JSONRequestValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Model\Users\MetadataDao;
use Model\Users\UserDao;
use Utils\Tools\CatUtils;

class UserController extends AbstractStatefulKleinController
{
    public function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
        $this->appendValidator(new JSONRequestValidator($this));
    }

    /**
     * Edit the user profile
     *
     */
    public function edit(): void
    {
        $json = $this->request->body();
        $json = json_decode($json, true);

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

            $userDao = new UserDao();
            $userDao->updateUser($user);
            $userDao->destroyCacheByUid($user->uid);

            AuthenticationHelper::refreshSession($_SESSION);

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

        $json = json_decode($json, true);

        $filtered = [];
        foreach ($json as $key => $value) {
            if (is_array($value)) {
                $filtered[$key] = filter_var($value, FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY);
            } else {
                $filtered[$key] = filter_var($value, $filters[$key], $options[$key]);
            }
        }

        if (!isset($filtered['key'])) {
            throw new InvalidArgumentException('`key` required', 400);
        }

        if (!isset($filtered['value'])) {
            throw new InvalidArgumentException('`value` required', 400);
        }

        try {
            $userMetaDao = new MetadataDao();
            $metadata = $userMetaDao->set(
                $this->user->uid,
                $filtered['key'],
                $filtered['value']
            );

            AuthenticationHelper::refreshSession($_SESSION);

            $this->response->json($metadata);
        } catch (Exception $exception) {
            $this->response->code($exception->getCode() > 0 ? $exception->getCode() : 500);

            $this->response->json([
                'error' => $exception->getMessage()
            ]);
        }
    }
}