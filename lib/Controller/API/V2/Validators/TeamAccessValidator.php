<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 15/02/17
 * Time: 17.01
 *
 */

namespace API\V2\Validators;


use API\V2\Exceptions\AuthorizationError;
use API\V2\KleinController;
use Teams\MembershipDao;

class TeamAccessValidator extends Base {

    public    $team;
    protected $controller;

    public function __construct( KleinController $controller ) {
        $this->controller = $controller;
        parent::__construct( $controller->getRequest() );
    }

    public function _validate() {

        $this->team = ( new MembershipDao() )->setCacheTTL( 60 * 10 )->findTeamByIdAndUser(
                $this->request->id_team, $this->controller->getUser()
        );

        if ( empty( $this->team ) ) {
            throw new AuthorizationError( "Not Authorized", 401 );
        }

        if ( method_exists($this->controller, 'setTeam') ) {
            $this->controller->setTeam( $this->team );
        }

    }

}