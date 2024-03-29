<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 07/02/17
 * Time: 12.12
 *
 */

namespace API\V2;

use API\V2\Json\Membership;
use API\V2\Validators\LoginValidator;
use API\V2\Validators\TeamAccessValidator;
use TeamModel;
use Teams\PendingInvitations;
use Teams\TeamDao;

class TeamMembersController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
        $this->appendValidator( new TeamAccessValidator( $this ) );
    }

    /**
     * Get team members list
     */
    public function index(){

        $pendingInvitation = new PendingInvitations( ( new \RedisHandler() )->getConnection(), [] );

        $team = ( new TeamDao() )->setCacheTTL( 60 * 60 * 24 )->findById( $this->request->id_team );
        $teamModel = new TeamModel( $team );
        $teamModel->updateMembersProjectsCount();

        $formatter = new Membership( $team->getMembers() ) ;
        $this->response->json( [
                'members' => $formatter->render(),
                'pending_invitations' => $pendingInvitation->hasPengingInvitation( $this->request->id_team )
        ] );

    }

    public function update() {
        $params = $this->request->paramsPost()->getIterator()->getArrayCopy();

        $params = filter_var_array($params, [
            'members' => [
                'filter' => FILTER_SANITIZE_EMAIL,
                'flags' => FILTER_REQUIRE_ARRAY
            ]
        ], true ) ;

        $teamStruct = ( new TeamDao() )
            ->findById( $this->request->id_team );

        $model = new TeamModel( $teamStruct ) ;
        $model->setUser( $this->user ) ;
        $model->addMemberEmails( $params['members'] ) ;
        $full_members_list = $model->updateMembers();

        $pendingInvitation = new PendingInvitations( ( new \RedisHandler() )->getConnection(), [] );
        $formatter = new Membership( $full_members_list ) ;
        $this->response->json( [
                'members' => $formatter->render(),
                'pending_invitations' => $pendingInvitation->hasPengingInvitation( $teamStruct->id )
        ] );

    }

    public function delete(){
        \Database::obtain()->begin();

        $teamStruct = ( new TeamDao() )
            ->findById( $this->request->id_team );

        $model = new TeamModel( $teamStruct ) ;
        $model->removeMemberUids( array( $this->request->uid_member ) );
        $model->setUser( $this->user ) ;
        $membersList = $model->updateMembers();

        $pendingInvitation = new PendingInvitations( ( new \RedisHandler() )->getConnection(), [] );
        $formatter = new Membership( $membersList ) ;
        $this->response->json( [
                'members' => $formatter->render(),
                'pending_invitations' => $pendingInvitation->hasPengingInvitation( $teamStruct->id )
        ] );

    }



}