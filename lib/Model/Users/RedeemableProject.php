<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 25/11/2016
 * Time: 14:33
 */

namespace Users;

use Exception;
use Model\Jobs\JobDao;
use Model\Projects\ProjectDao;
use Routes;
use Users_UserStruct;

class RedeemableProject {
    /**
     * @var Users_UserStruct
     */
    protected Users_UserStruct $user;
    protected array            $session;

    /**
     * @var \Model\Projects\ProjectStruct
     */
    protected $project;

    public function __construct( Users_UserStruct $user, array &$session ) {
        $this->user    = $user;
        $this->session =& $session;
    }

    public function isPresent() {
        return $this->__getProject() != null;
    }

    public function __getProject() {
        if ( !isset( $this->project ) ) {
            if ( isset( $this->session[ 'last_created_pid' ] ) ) {
                $this->project = ProjectDao::findById( $this->session[ 'last_created_pid' ] );
            }
        }

        return $this->project;
    }

    public function isRedeemable() {
        return isset( $this->session[ 'redeem_project' ] ) && $this->session[ 'redeem_project' ] === true;
    }

    public function redeem() {
        if ( $this->isPresent() && $this->isRedeemable() ) {

            $this->project->id_customer = $this->user->getEmail();
            $this->project->id_team     = $this->user->getPersonalTeam()->id;
            $this->project->id_assignee = $this->user->getUid();

            ProjectDao::updateStruct( $this->project, [
                    'fields' => [ 'id_team', 'id_customer', 'id_assignee' ]
            ] );

            ( new JobDao() )->updateOwner( $this->project, $this->user );
        }

        $this->clear();
    }

    public function clear() {
        unset( $this->session[ 'redeem_project' ] );
        unset( $this->session[ 'last_created_pid' ] );
        unset( $_SESSION[ 'redeem_project' ] );
        unset( $_SESSION[ 'last_created_pid' ] );
    }

    public function getProject() {
        return $this->project;
    }

    public function tryToRedeem() {
        if ( $this->isPresent() && $this->isRedeemable() ) {
            $this->redeem();
        }
    }

    /**
     * @throws Exception
     */
    public function getDestinationURL(): ?string {
        if ( $this->isPresent() ) {
            return Routes::analyze( [
                    'project_name' => $this->project->name,
                    'id_project'   => $this->project->id,
                    'password'     => $this->project->password
            ] );
        } else {
            return null;
        }
    }

}