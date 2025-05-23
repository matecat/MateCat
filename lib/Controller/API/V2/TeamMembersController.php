<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 07/02/17
 * Time: 12.12
 *
 */

namespace API\V2;

use AbstractControllers\KleinController;
use API\Commons\Validators\LoginValidator;
use API\Commons\Validators\TeamAccessValidator;
use API\V2\Json\Membership;
use Exception;
use RedisHandler;
use ReflectionException;
use TeamModel;
use Teams\PendingInvitations;
use Teams\TeamDao;

class TeamMembersController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
        $this->appendValidator( new TeamAccessValidator( $this ) );
    }

    /**
     * Get the team members list
     * @throws ReflectionException
     */
    public function index(){

        $pendingInvitation = new PendingInvitations( ( new RedisHandler() )->getConnection(), [] );

        $team = ( new TeamDao() )->setCacheTTL( 60 * 60 * 24 )->findById( $this->request->param( 'id_team' ) );
        $teamModel = new TeamModel( $team );
        $teamModel->updateMembersProjectsCount();

        $formatter = new Membership( $team->getMembers() ) ;
        $this->response->json( [
                'members' => $formatter->render(),
                'pending_invitations' => $pendingInvitation->hasPengingInvitation( $this->request->param( 'id_team' ) )
        ] );

    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function update() {
        $params = $this->request->paramsPost()->getIterator()->getArrayCopy();

        $params = filter_var_array($params, [
            'members' => [
                'filter' => FILTER_SANITIZE_EMAIL,
                'flags' => FILTER_REQUIRE_ARRAY
            ]
        ], true ) ;

        $teamStruct = ( new TeamDao() )
            ->findById( $this->request->param( 'id_team' ) );

        $model = new TeamModel( $teamStruct ) ;
        $model->setUser( $this->user ) ;
        $model->addMemberEmails( $params['members'] ) ;
        $full_members_list = $model->updateMembers();

        $pendingInvitation = new PendingInvitations( ( new \RedisHandler() )->getConnection(), [] );
        $formatter = new Membership( $full_members_list ) ;

        $this->refreshClientSessionIfNotApi();

        $this->response->json( [
                'members' => $formatter->render(),
                'pending_invitations' => $pendingInvitation->hasPengingInvitation( $teamStruct->id )
        ] );

    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function delete(){
        \Database::obtain()->begin();

        $teamStruct = ( new TeamDao() )
            ->findById( $this->request->param( 'id_team' ) );

        $model = new TeamModel( $teamStruct ) ;
        $model->removeMemberUids( array( $this->request->param( 'uid_member' ) ) );
        $model->setUser( $this->user ) ;
        $membersList = $model->updateMembers();

        $pendingInvitation = new PendingInvitations( ( new RedisHandler() )->getConnection(), [] );
        $formatter = new Membership( $membersList ) ;

        $this->refreshClientSessionIfNotApi();

        $this->response->json( [
                'members' => $formatter->render(),
                'pending_invitations' => $pendingInvitation->hasPengingInvitation( $teamStruct->id )
        ] );

    }



}