<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 30/01/2017
 * Time: 18:09
 */

namespace API\V2;


use API\App\RateLimiterTrait;
use API\Commons\Exceptions\AuthenticationError;
use API\Commons\Exceptions\NotFoundException;
use API\Commons\KleinController;
use ApiKeys_ApiKeyDao;
use Exception;
use Klein\Response;
use Utils;

class KeyCheckController extends KleinController {

    use RateLimiterTrait;

    /**
     * @throws AuthenticationError
     */
    public function ping() {
        if ( !$this->api_record ) {
            throw new AuthenticationError();
        }

        $this->response->code( 200 );
    }

    /**
     * @throws NotFoundException
     * @throws AuthenticationError
     * @throws Exception
     */
    public function getUID() {

        $checkRateLimitEmail = $this->checkRateLimitResponse( $this->response, $this->getUser()->email ?? "BLANK_EMAIL", '/api/v2/user/[:user_api_key]', 3 );
        $checkRateLimitIp    = $this->checkRateLimitResponse( $this->response, Utils::getRealIpAddr() ?? "127.0.0.1", '/api/v2/user/[:user_api_key]', 3 );

        if ( $checkRateLimitEmail instanceof Response ) {
            $this->response = $checkRateLimitEmail;

            return;
        }

        if ( $checkRateLimitIp instanceof Response ) {
            $this->response = $checkRateLimitIp;

            return;
        }

        if ( !$this->api_record ) {
            $this->incrementRateLimitCounter( $this->getUser()->email ?? "BLANK_EMAIL", '/api/v2/user/[:user_api_key]' );
            $this->incrementRateLimitCounter( Utils::getRealIpAddr() ?? "127.0.0.1", '/api/v2/user/[:user_api_key]' );
            throw new AuthenticationError( 'Unauthorized', 401 );
        }

        [ $user_api_key, $user_api_secret ] = explode( '-', $this->params[ 'user_api_key' ] );

        if ( $user_api_key && $user_api_secret ) {

            $api_record = ApiKeys_ApiKeyDao::findByKey( $user_api_key );

            if ( $api_record && $api_record->validSecret( $user_api_secret ) ) {

                /*
                    //for now the response is really simple, if more info are needed use the DAO
                    $dao = new Users_UserDao();
                    $dao->setCacheTTL( 3600 );
                    $user = $dao->getByUid( $api_record->uid ) ;
                    $userJson = [ 'user' => User::renderItem( $user ) ]:
                */

                $userJson = [ 'user' => [ 'uid' => (int)$api_record->uid ] ];
                $this->response->json( $userJson );

                return;

            }

        }

        $this->incrementRateLimitCounter( $this->getUser()->email ?? "BLANK_EMAIL", '/api/v2/user/[:user_api_key]' );
        $this->incrementRateLimitCounter( Utils::getRealIpAddr() ?? "127.0.0.1", '/api/v2/user/[:user_api_key]' );

        throw new NotFoundException( "User not found.", 404 );

    }

}