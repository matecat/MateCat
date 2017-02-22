<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/02/17
 * Time: 13.04
 *
 */

namespace API\App;


use Organizations\InvitedUser;

/**
 * Endpoint to get the call from emails link in the invitation emails
 *
 * Class OrganizationsInvitationsController
 * @package API\App
 */
class OrganizationsInvitationsController  extends AbstractStatefulKleinController {

    public function collectBackInvitation(){

        $invite = new InvitedUser( $this->request->jwt, $this->response );
        $invite->prepareUserInvitedSignUpRedirect();
        $this->response->redirect( \Routes::appRoot() ) ;

    }

}