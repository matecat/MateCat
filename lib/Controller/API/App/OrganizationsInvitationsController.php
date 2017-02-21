<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/02/17
 * Time: 13.04
 *
 */

namespace API\App;


use Organizations\OrganizationInvitedUser;

class OrganizationsInvitationsController  extends AbstractStatefulKleinController {

    public function collectBackInvitation(){

        $invite = new OrganizationInvitedUser( $this->request->jwt, $this->response );
        $invite->prepareUserInvitedSignUp();
        $this->response->redirect( \Routes::appRoot() ) ;

    }

}