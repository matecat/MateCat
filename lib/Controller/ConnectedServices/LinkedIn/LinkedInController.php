<?php

namespace ConnectedServices\LinkedIn;

use API\V2\KleinController;
use AuthCookie;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Routes;
use Users\MetadataDao;
use Users_UserDao;
use Users_UserStruct;

class LinkedInController extends KleinController {


    /**
     * Handle response from LinkedIn
     */
    public function response()
    {
        $code = $this->request->param( 'code' );

        if($code){
            try {
                $token = LinkedInClient::getAuthToken($code);
                $user = LinkedInClient::getResourceOwner($token);
                $usersDao = new Users_UserDao();
                $userExists = $usersDao->getByEmail($user->email);

                // create user if not exists
                if(!$userExists){
                    $newUser = new Users_UserStruct();
                    $newUser->email = $user->email;
                    $newUser->first_name = $user->given_name;
                    $newUser->last_name = $user->family_name;

                    $newUser = $usersDao->createUser($newUser);

                    $userMetaDao = new MetadataDao();
                    $userMetaDao->set($newUser->uid, 'picture', $user->picture);

                    $newUser->oauth_access_token = $token;
                    $usersDao->updateUser($newUser);

                    $uid = $newUser->uid;
                } else {
                    $uid = $userExists->uid;
                }

                // login
                AuthCookie::setCredentials( $user->email, $uid );

                // redirect to homepage
                return $this->response->redirect( Routes::appRoot() );

            } catch (Exception $exception){
                $this->response->code($exception->getCode());

                return $this->response->json( [
                    "success" => false,
                    "error_msg" => $exception->getMessage(),
                    "error_class" => LinkedInController::class,
                    "error_code" => $exception->getCode(),
                ] );

            } catch (GuzzleException $exception) {
                $this->response->code($exception->getCode());

                return $this->response->json( [
                    "success" => false,
                    "error_msg" => $exception->getMessage(),
                    "error_class" => LinkedInController::class,
                    "error_code" => $exception->getCode(),
                ] );
            }
        }

        $this->response->code(400);

        return $this->response->json( [
            "success" => false,
            "error_msg" => 'Malformed request. Please add `code` param to the request. "',
            "error_class" => LinkedInController::class,
            "error_code" => -1,
        ] );
    }
}