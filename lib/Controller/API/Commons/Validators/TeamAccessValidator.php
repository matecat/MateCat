<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 15/02/17
 * Time: 17.01
 *
 */

namespace API\Commons\Validators;


use AbstractControllers\KleinController;
use API\Commons\Exceptions\AuthorizationError;
use Teams\MembershipDao;

class TeamAccessValidator extends Base {

    public    $team;
    protected $controller;

    public function __construct( KleinController $controller ) {
        $this->controller = $controller;
        parent::__construct( $controller->getRequest() );
    }

    public function _validate() {

        $id_team = $this->request->id_team;
        $name    = ( !empty( $this->request->team_name ) ) ? base64_decode( $this->request->team_name ) : null;

        if ( $name !== null and $name !== 'Personal' ) {
            $this->team = ( new MembershipDao() )->setCacheTTL( 60 * 10 )->findTeamByIdAndName(
                    $id_team,
                    $name
            );
        } else {
            $this->team = ( new MembershipDao() )->setCacheTTL( 60 * 10 )->findTeamByIdAndUser(
                    $id_team,
                    $this->controller->getUser()
            );
        }

        if ( empty( $this->team ) ) {
            throw new AuthorizationError( "Not Authorized", 401 );
        }

        if ( method_exists( $this->controller, 'setTeam' ) ) {
            $this->controller->setTeam( $this->team );
        }

    }

}