<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 25/11/2016
 * Time: 14:33
 */

namespace Users;

use Routes ;

use Projects_ProjectDao, Jobs_JobDao ;

class RedeemableProject
{
    /**
     * @var \Users_UserStruct
     */
    protected $user ;
    protected $session ;

    /**
     * @var \Projects_ProjectStruct
     */
    protected $project ;

    public function __construct( $user, $session ) {
        $this->user = $user ;
        $this->session = $session ;
    }

    public function isPresent() {
        return $this->__getProject() != NULL ;
    }

    public function __getProject() {
        if ( !isset( $this->project ) ) {
            if ( isset( $this->session['last_created_pid'] ) ) {
                $this->project = Projects_ProjectDao::findById( $this->session['last_created_pid'] ) ;
            }
        }
        return $this->project ;
    }

    public function isRedeemable() {
        return isset( $this->session['redeem_project']) && $this->session['redeem_project'] === TRUE ;
    }

    public function redeem() {
        if ( $this->isPresent() && $this->isRedeemable() ) {

            $this->project->id_customer = $this->user->email ;
            $this->project->id_organization = $this->user->getPersonalOrganization()->id ;

            Projects_ProjectDao::updateStruct( $this->project, array(
                'fields' => array( 'id_organization', 'id_customer' )
            ) ) ;

            ( new Jobs_JobDao() )->updateOwner( $this->project, $this->user );
        }


        $this->clear();
    }

    public function clear() {
        unset( $this->session['redeem_project'] );
        unset( $this->session['last_created_pid'] );
    }

    public function getProject() {
        return $this->project ;
    }

    public function tryToRedeem() {
        if ( $this->isPresent() && $this->isRedeemable() ) {
            $this->redeem() ;
        }
    }

    public function getDestinationURL() {
        if ( $this->isPresent() ) {
            return Routes::analyze( array(
                'project_name'  => $this->project->name,
                'id_project'    => $this->project->id,
                'password'      => $this->project->password
            )) ;
        }
        else {
            return NULL ;
        }
    }

}