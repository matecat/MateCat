<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\TeamAccessValidator;
use Model\Teams\MembershipDao;
use ReflectionException;
use View\API\V2\Json\Membership;

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