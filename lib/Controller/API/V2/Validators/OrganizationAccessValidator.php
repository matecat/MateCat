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
use Organizations\MembershipDao;

class OrganizationAccessValidator extends Base {

    public $organization;
    protected $controller;

    public function __construct( KleinController $controller ) {
        $this->controller = $controller;
        parent::__construct( $controller->getRequest() );
    }

    public function validate() {

        if ( !$this->controller->getUser() ) {
            throw new AuthorizationError('Not Authorized', 401);
        }

        $this->organization = ( new MembershipDao() )->findOrganizationByIdAndUser(
                $this->request->id_organization, $this->controller->getUser()
        );

        if ( empty( $this->organization ) ) {
            throw new AuthorizationError( "Not Authorized", 401 );
        }

        if ( method_exists($this->controller, 'setOrganization') ) {
            $this->controller->setOrganization( $this->organization );
        }

    }

}