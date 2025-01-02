<?php

namespace API\V2;

use API\Commons\AbstractStatefulKleinController;
use API\Commons\Authentication\AuthenticationHelper;
use API\Commons\Validators\JSONRequestValidator;
use API\Commons\Validators\LoginValidator;
use InvalidArgumentException;
use Users\MetadataDao;
use Users_UserDao;

class UserController extends AbstractStatefulKleinController
{
    public function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
        $this->appendValidator( new JSONRequestValidator( $this ) );
    }

    /**
     * Edit the user profile
     *
     * @return \Klein\Response
     */
    public function edit(){

        $json = $this->request->body();
        $json = json_decode($json, true);

        $user = filter_var_array(
            $json,
            [
                'first_name' => [
                    'filter' => FILTER_CALLBACK, 'options' => function ( $username ) {
                        return mb_substr( preg_replace( '/(?:https?|s?ftp)?\P{L}+/', '', $username ), 0, 50 );
                    }
                ],
                'last_name'  => [
                    'filter' => FILTER_CALLBACK, 'options' => function ( $username ) {
                        return mb_substr( preg_replace( '/(?:https?|s?ftp)?\P{L}+/', '', $username ), 0, 50 );
                    }
                ],
            ]
        );


        try {
            if(!isset($user['first_name'])){
                throw new InvalidArgumentException('`first_name` required', 400);
            }

            if(!isset($user['last_name'])){
                throw new InvalidArgumentException('`last_name` required', 400);
            }

            $first_name = trim($user['first_name']);
            $last_name = trim($user['last_name']);

            if(empty($first_name)){
                throw new InvalidArgumentException('`first_name` cannot be empty', 400);
            }

            if(empty($last_name)){
                throw new InvalidArgumentException('`last_name` cannot be empty', 400);
            }

            $user = $this->user;
            $user->first_name = $first_name;
            $user->last_name = $last_name;

            $userDao = new Users_UserDao();
            $userDao->updateUser($user);
            $userDao->destroyCacheByUid($user->uid);

            return $this->response->json([
                'uid' => $user->uid,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'create_date' => $user->create_date,
            ]);

        } catch (\Exception $exception){
            $this->response->code($exception->getCode());
            return $this->response->json([
                'error' => $exception->getMessage()
            ]);
        }
    }

    /**
     * @return \Klein\Response
     */
    public function setMetadata(){
        $json = $this->request->body();

        $filters = [
            'key'   => FILTER_SANITIZE_STRING,
            'value' => FILTER_SANITIZE_STRING,
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
        foreach($json as $key => $value) {
            if(is_array($value)){
                $filtered[$key] = filter_var($value, FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY);
            } else {
                $filtered[$key] = filter_var($value, $filters[$key], $options[$key]);
            }
        }

        if(!isset($filtered['key'])){
            throw new InvalidArgumentException('`key` required', 400);
        }

        if(!isset($filtered['value'])){
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

            return $this->response->json($metadata);

        } catch (\Exception $exception){
            $this->response->code($exception->getCode() > 0 ? $exception->getCode() : 500);

            return $this->response->json([
                'error' => $exception->getMessage()
            ]);
        }
    }
}