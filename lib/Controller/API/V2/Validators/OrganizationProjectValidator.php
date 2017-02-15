<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/02/2017
 * Time: 10:57
 */

namespace API\V2\Validators;


use API\V2\KleinController;
use Klein\Request;
use Organizations\OrganizationStruct;
use Organizations\MembershipDao;

use API\V2\Exceptions\AuthorizationError;

class OrganizationProjectValidator extends Base {

    /**
     * @var \Projects_ProjectStruct
     */
    public $project;

    /**
     * @var OrganizationStruct
     */
    public $organization;

    public function __construct( KleinController $controller ) {
        parent::__construct( $controller->getRequest() );
    }

    public function validate( $user ) {

        $this->project = \Projects_ProjectDao::findById( $this->request->id_project );

        $this->organization = ( new MembershipDao() )->findOrganizationByIdAndUser(
                $this->project->id_organization, $user
        );

        if ( empty( $this->organization ) ) {
            throw new AuthorizationError( "Not Authorized", 401 );
        }


    }

}