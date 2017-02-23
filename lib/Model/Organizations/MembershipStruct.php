<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:45
 */

namespace Organizations;

use DataAccess_AbstractDaoSilentStruct ;
use DataAccess_IDaoStruct ;

class MembershipStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $id ;
    public $id_organization;
    public $uid ;
    public $is_admin ;

    /**
     * @var \Users_UserStruct
     */
    private $user ;

    /**
     * @var OrganizationStruct
     */
    private $organization ;


    /**
     * @var
     */
    private $user_metadata ;


    public function setUser( \Users_UserStruct $user ) {
        $this->user = $user ;
    }


    public function setUserMetadata($user_metadata) {
        $this->user_metadata = $user_metadata ;
    }

    public function getUserMetadata() {
        return $this->user_metadata ;
    }

    /**
     * @return \Users_UserStruct|null
     */
    public function getUser( ) {
        if ( is_null($this->user) ) {
            $this->user = ( new \Users_UserDao() )->getByUid( $this->uid );
        }
        return $this->user ;
    }

    /**
     * @return OrganizationStruct
     */
    public function getOrganization() {
        if ( is_null( $this->organization ) ) {
            $this->organization = ( new OrganizationDao() )->findById( $this->id_organization ) ;
        }
        return $this->organization;
    }

}