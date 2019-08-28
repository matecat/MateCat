<?php

namespace API\V2\Validators;

use API\V2\Exceptions\AuthorizationError;
use API\V2\KleinController;
use Exception;
use Teams\MembershipDao;
use Teams\TeamStruct;

class ProjectAccessValidator extends Base {

    /**
     * @var TeamStruct
     */
    private $team;

    /**
     * @var \Projects_ProjectStruct
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
    public function __construct( KleinController $controller ) {
        $this->controller = $controller;
        parent::__construct( $controller->getRequest() );
    }

    /**
     * @param \Projects_ProjectStruct $project
     *
     * @return $this
     */
    public function setProject( \Projects_ProjectStruct $project ) {
        $this->project = $project;

        return $this;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    protected function _validate() {
        $this->team = ( new MembershipDao() )->setCacheTTL( 60 * 10 )->findTeamByIdAndUser(
                $this->project->id_team, $this->controller->getUser()
        );

        if ( empty( $this->team ) ) {
            throw new AuthorizationError( "Not Authorized, the user does not belong to team " . $this->project->id_team, 401 );
        }

        if ( method_exists($this->controller, 'setTeam') ) {
            $this->controller->setTeam( $this->team );
        }
    }
}