<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 30/01/2017
 * Time: 18:09
 */

namespace API\V2;


use API\Commons\Exceptions\AuthenticationError;
use API\Commons\Exceptions\NotFoundException;
use API\Commons\KleinController;
use ApiKeys_ApiKeyDao;

class KeyCheckController extends KleinController {

    /**
     * @throws AuthenticationError
     */
    public function ping() {
        if ( !$this->api_record ) {
            throw new AuthenticationError() ;
        }

        $this->response->code(200) ;
    }

    /**
     * @throws NotFoundException
     * @throws AuthenticationError
     */
    public function getUID(){

        if ( !$this->api_record ) {
            throw new AuthenticationError( 'Unauthorized', 401 ) ;
        }

        [ $user_api_key, $user_api_secret ] = explode('-', $this->params[ 'user_api_key' ] ) ;

        if ( $user_api_key && $user_api_secret ) {

            $api_record = ApiKeys_ApiKeyDao::findByKey( $user_api_key );

            if( $api_record && $api_record->validSecret( $user_api_secret ) ){

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

        throw new NotFoundException( "User not found.", 404 );

    }

}