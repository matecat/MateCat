<?php

namespace API\Commons\Validators;

use AbstractControllers\KleinController;
use API\Commons\Exceptions\AuthorizationError;
use Projects_ProjectStruct;
use ReflectionException;
use Teams\MembershipDao;

class ProjectAccessValidator extends Base {

    /**
     * @var Projects_ProjectStruct
     */
    private Projects_ProjectStruct $project;

    /**
     * Class constructor.
     *
     * @param KleinController        $controller The KleinController object.
     * @param Projects_ProjectStruct $project    The Projects_ProjectStruct object.
     */
    public function __construct( KleinController $controller, Projects_ProjectStruct $project ) {
        parent::__construct( $controller );
        $this->project = $project;
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
     * @return void
     * @throws AuthorizationError If a user is not logged-in or if the user does not belong to the team.
     * @throws ReflectionException
     */
    protected function _validate(): void {

        if ( empty( $this->controller->getUser() ) ) {
            throw new AuthorizationError( "Not Authorized. You must be logged in.", 401 );
        }

        $team = ( new MembershipDao() )->setCacheTTL( 60 * 10 )->findTeamByIdAndUser(
                $this->project->id_team, $this->controller->getUser()
        );

        if ( empty( $team ) ) {
            throw new AuthorizationError( "Not Authorized, the user does not belong to team " . $this->project->id_team, 401 );
        }

        if ( method_exists( $this->controller, 'setTeam' ) ) {
            $this->controller->setTeam( $team );
        }
    }
}