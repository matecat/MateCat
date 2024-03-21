<?php

namespace API\V2\Validators;

use API\V2\Exceptions\AuthorizationError;
use API\V2\KleinController;
use Projects_ProjectStruct;
use Teams\MembershipDao;
use Teams\TeamStruct;

class ProjectAccessValidator extends Base {

    /**
     * @var TeamStruct
     */
    private $team;

    /**
     * @var Projects_ProjectStruct
     */
    private $project;

    /**
     * @var KleinController
     */
    protected $controller;

    /**
     * Class constructor.
     *
     * @param KleinController        $controller The KleinController object.
     * @param Projects_ProjectStruct $project    The Projects_ProjectStruct object.
     */
    public function __construct( KleinController $controller, Projects_ProjectStruct $project ) {
        $this->controller = $controller;
        $this->project    = $project;
        parent::__construct( $controller->getRequest() );
    }


    /**
     * Validates the user's access to the project.
     *
     * This function performs a sequence of steps to verify the user's access:
     * - It checks if the user is logged-in. If not, an AuthorizationError is thrown.
     * - It tries to find the team associated with the project and the current user.
     *   If no such team exists, an AuthorizationError is thrown.
     * - If a 'setTeam' method exists on the controller, the found team is set on the controller.
     *
     * @throws AuthorizationError If a user is not logged-in or if the user does not belong to the team.
     * @return void
     */
    protected function _validate() {

        if( empty( $this->controller->getUser() ) ){
            throw new AuthorizationError( "Not Authorized. You must be logged in.", 401 );
        }

        $this->team = ( new MembershipDao() )->setCacheTTL( 60 * 10 )->findTeamByIdAndUser(
                $this->project->id_team, $this->controller->getUser()
        );

        if ( empty( $this->team ) ) {
            throw new AuthorizationError( "Not Authorized, the user does not belong to team " . $this->project->id_team, 401 );
        }

        if ( method_exists( $this->controller, 'setTeam' ) ) {
            $this->controller->setTeam( $this->team );
        }
    }
}