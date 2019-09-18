<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 04/07/2018
 * Time: 16:17
 */


namespace API\App;

use API\V2\Json\Membership;
use API\V2\KleinController;
use Teams\MembershipDao;

class TeamPublicMembersController extends KleinController {

    /**
     * Get team members list
     */
    public function publicList(){

        $memberships = ( new MembershipDao() )->setCacheTTL( 60 * 60 * 24 )->getMemberListByTeamId( $this->request->id_team );

        $formatter = new Membership( $memberships ) ;
        $this->response->json( $formatter->renderPublic() );

    }




}