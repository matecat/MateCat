<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 22/11/2016
 * Time: 09:38
 */

namespace API\App;

use AuthCookie;
use Users\RedeemableProject;
use Users_UserDao;

class LoginController extends AbstractStatefulKleinController  {

    public function logout() {
        unset( $_SESSION[ 'cid' ] );
        AuthCookie::destroyAuthentication();
        $this->response->code(200);
    }

    public function login() {
        $params = filter_var_array( $this->request->params(), [
                'email'    => FILTER_SANITIZE_EMAIL,
                'password' => FILTER_SANITIZE_STRING
        ] );

        $dao  = new Users_UserDao();
        $user = $dao->getByEmail( $params[ 'email' ] );

        if ( $user && ( !is_null( $user->email_confirmed_at ) || !is_null( $user->oauth_access_token ) ) && $user->passwordMatch( $params[ 'password' ] ) ) {
            AuthCookie::setCredentials( $user->email, $user->uid );

            $project = new RedeemableProject( $user, $_SESSION );

            $project->tryToRedeem();
            $this->response->code( 200 );
        } else {
            $this->response->code( 404 );
        }

    }

}