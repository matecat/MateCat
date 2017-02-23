<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/02/17
 * Time: 15.05
 *
 */

namespace Organizations;


use API\V2\Exceptions\ValidationError;
use FlashMessage;
use Klein\Response;

class InvitedUser {

    /**
     * @var string
     */
    protected $jwt;

    protected $response;

    public function __construct( $jwt, Response $response ) {

        try {
            $this->jwt = \SimpleJWT::getValidPayload( $jwt );
        } catch ( \DomainException $e ) {
            throw new ValidationError( $e->getMessage(), $e->getCode(), $e );
        }

        $this->response = $response;

    }

    public function prepareUserInvitedSignUpRedirect() {

        $_SESSION[ 'invited_to_organization' ] = $this->jwt;
        FlashMessage::set( 'popup', 'signup', FlashMessage::SERVICE );
        FlashMessage::set( 'signup_email', $this->jwt[ 'email' ], FlashMessage::SERVICE );

    }

    public static function completeOrganizationSignUp( $user, $invitation ){

        $organizationStruct = ( new OrganizationDao )->findById( $invitation[ 'organization_id' ] );

        $organizationModel = new \OrganizationModel( $organizationStruct );
        $organizationModel->setUser( $user );
        $organizationModel->addMemberEmail( $invitation[ 'email' ] );
        $organizationModel->updateMembers();

        $pendingInvitation = new PendingInvitations( ( new \RedisHandler() )->getConnection(), $invitation );
        $pendingInvitation->remove();

        unset( $_SESSION[ 'invited_to_organization' ] );

    }

    public static function hasPendingInvitations(){
        return isset( $_SESSION[ 'invited_to_organization' ] ) && !empty( $_SESSION[ 'invited_to_organization' ][ 'organization_id' ] );
    }

}