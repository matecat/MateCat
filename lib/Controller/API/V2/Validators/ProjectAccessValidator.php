<?php

namespace API\V2\Validators;

use API\V2\Exceptions\AuthorizationError;
use API\V2\KleinController;
use Exception;
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
     * ProjectAccessValidator constructor.
     *
     * @param KleinController $controller
     */
    public function __construct( KleinController $controller, Projects_ProjectStruct $project ) {
        $this->controller = $controller;
        $this->project    = $project;
        parent::__construct( $controller->getRequest() );
    }

    /**
     * @return mixed
     * @throws Exception
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