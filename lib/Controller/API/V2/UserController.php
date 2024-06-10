<?php

namespace API\V2;

use API\V2\Validators\JSONRequestValidator;
use API\V2\Validators\LoginValidator;
use InvalidArgumentException;
use Users_UserDao;

class UserController extends KleinController
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

        $filters = [
            'first_name'      => FILTER_SANITIZE_STRING,
            'last_name' => FILTER_SANITIZE_STRING,
        ];

        $options = [
            'first_name' => [
                'flags' => FILTER_NULL_ON_FAILURE
            ],
            'last_name' => [
                'flags' => FILTER_NULL_ON_FAILURE
            ],
        ];

        $json = json_decode($json, true);

        $filtered = [];
        foreach($json as $key => $value) {
            $filtered[$key] = filter_var($value, $filters[$key], $options[$key]);
        }

        try {
            if(!isset($filtered['first_name'])){
                throw new InvalidArgumentException('`first_name` required', 400);
            }

            if(!isset($filtered['last_name'])){
                throw new InvalidArgumentException('`last_name` required', 400);
            }

            $user = $this->user;
            $user->first_name = $filtered['first_name'];
            $user->last_name = $filtered['last_name'];

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
}