<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/02/2017
 * Time: 10:57
 */

namespace API\V2\Validators;


use API\V2\KleinController;
use Exceptions\NotFoundError;
use Klein\Request;
use Organizations\OrganizationStruct;
use Organizations\MembershipDao;

use API\V2\Exceptions\AuthorizationError;

class OrganizationProjectValidator extends Base {

    /**
     * @var \Projects_ProjectStruct
     */
    public $controller;

    public function __construct( KleinController $controller ) {
        $this->controller = $controller;
        parent::__construct( $controller->getRequest() );
    }

    public function validate() {

        if ( empty( $this->controller->project ) ) {
            throw new NotFoundError( "Not Found", 404 );
        }

    }

}