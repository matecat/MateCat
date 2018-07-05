<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 04/07/2018
 * Time: 16:17
 */


namespace API\App;

use API\V2\KleinController;
use API\V2\Json\Membership;
use API\V2\Validators\LoginValidator;
use API\V2\Validators\TeamAccessValidator;
use TeamModel;
use Teams\TeamDao;

class TeamPublicMembersController extends KleinController {

    /**
     * Get team members list
     */
    public function publicList(){

        $team = ( new TeamDao() )->setCacheTTL( 60 * 60 * 24 )->findById( $this->request->id_team );
        $teamModel = new TeamModel( $team );
        $teamModel->updateMembersProjectsCount();

        $formatter = new Membership( $team->getMembers() ) ;
        $this->response->json( $formatter->renderPublic() );

    }




}