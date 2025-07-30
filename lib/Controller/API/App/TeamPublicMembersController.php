<?php

namespace API\App;

use AbstractControllers\KleinController;
use API\Commons\Validators\LoginValidator;
use API\Commons\Validators\TeamAccessValidator;
use API\V2\Json\Membership;
use Teams\MembershipDao;

class TeamPublicMembersController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
        $this->appendValidator( new TeamAccessValidator( $this ) );
    }

    /**
     * Get team members list
     */
    public function publicList() {
        $memberships = ( new MembershipDao() )->setCacheTTL( 60 * 60 * 24 )->getMemberListByTeamId( $this->request->id_team );
        $formatter   = new Membership( $memberships );
        $this->response->json( $formatter->renderPublic() );
    }

}