<?php

namespace ConnectedServices\Microsoft;

use API\V2\KleinController;
use AuthCookie;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Routes;
use Stevenmaguire\OAuth2\Client\Provider\MicrosoftResourceOwner;
use Users\MetadataDao;
use Users_UserDao;
use Users_UserStruct;

class MicrosoftController extends KleinController
{
    /**
     * Handle response from Microsoft
     */
    public function response()
    {
        $code = $this->request->param( 'code' );

        if($code){
            try {
                $token = MicrosoftClient::getAuthToken($code);

                /** @var MicrosoftResourceOwner $user */
                $user = MicrosoftClient::getResourceOwner($token);
                $usersDao = new Users_UserDao();
                $userExists = $usersDao->getByEmail($user->getEmail());

                // create user if not exists
                if(!$userExists){
                    $newUser = new Users_UserStruct();
                    $newUser->email = $user->getEmail();
                    $newUser->first_name = $user->getFirstname();
                    $newUser->last_name = $user->getLastname();

                    $newUser = $usersDao->createUser($newUser);

                    $userMetaDao = new MetadataDao();
                    $userMetaDao->set($newUser->uid, 'picture', $user->getUrls());

                    $newUser->oauth_access_token = $token->getToken();
                    $usersDao->updateUser($newUser);
                    $usersDao->destroyCacheByUid($newUser->uid);

                    $uid = $newUser->uid;
                } else {
                    $uid = $userExists->uid;
                }

                // login
                AuthCookie::setCredentials( $user->getEmail(), $uid );

                // redirect to homepage
                return $this->response->redirect( Routes::appRoot() );

            } catch (Exception $exception){
                $this->response->code($exception->getCode());

                return $this->response->json( [
                    "success" => false,
                    "error_msg" => $exception->getMessage(),
                    "error_class" => MicrosoftController::class,
                    "error_code" => $exception->getCode(),
                ] );

            } catch (GuzzleException $exception) {
                $this->response->code($exception->getCode());

                return $this->response->json( [
                    "success" => false,
                    "error_msg" => $exception->getMessage(),
                    "error_class" => MicrosoftController::class,
                    "error_code" => $exception->getCode(),
                ] );
            }
        }

        $this->response->code(400);

        return $this->response->json( [
            "success" => false,
            "error_msg" => 'Malformed request. Please add `code` param to the request. "',
            "error_class" => MicrosoftController::class,
            "error_code" => -1,
        ] );
    }
}