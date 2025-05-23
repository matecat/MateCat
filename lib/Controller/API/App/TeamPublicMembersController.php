<?php

namespace API\App;

use AbstractControllers\KleinController;
use API\Commons\Validators\LoginValidator;
use API\Commons\Validators\TeamAccessValidator;
use API\V2\Json\Membership;
use ReflectionException;
use Teams\MembershipDao;

class TeamPublicMembersController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
        $this->appendValidator( new TeamAccessValidator( $this ) );
    }

    /**
     * Get a team members list
     * @throws ReflectionException
     */
    public function publicList() {
        $memberships = ( new MembershipDao() )->setCacheTTL( 60 * 60 * 24 )->getMemberListByTeamId( $this->request->param( 'id_team' ) );
        $formatter   = new Membership( $memberships );
        $this->response->json( $formatter->renderPublic() );
    }

}