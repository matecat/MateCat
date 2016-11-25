<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 22/11/2016
 * Time: 09:38
 */

namespace API\App;

use API\V2\KleinController;

class LoginController extends AbstractStatefulKleinController  {


    public function login() {
        $params = filter_var_array( $this->request->params(), array(
            'email' => FILTER_SANITIZE_EMAIL,
            'password' => FILTER_SANITIZE_STRING
        ));

        $dao = new \Users_UserDao() ;
        $user = $dao->getByEmail( $params['email'] ) ;

        if ( $user && !is_null($user->email_confirmed_at) && $user->passwordMatch( $params['password'] ) ) {
            \AuthCookie::setCredentials($user->email, $user->uid ) ;
            \Utils::tryToRedeemProject( $user->email ) ;
            $this->response->code( 200 ) ;
        }
        else {
            $this->response->code( 404 ) ;
        }

    }

    protected function afterConstruct()
    {
    }
}