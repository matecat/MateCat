<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/02/17
 * Time: 15.05
 *
 */

namespace Model\Teams;


use Controller\Abstracts\FlashMessage;
use Controller\API\Commons\Exceptions\ValidationError;
use DomainException;
use Klein\Response;
use ReflectionException;
use Utils\Redis\RedisHandler;
use Utils\Tools\SimpleJWT;

class InvitedUser {

    /**
     * @var string
     */
    protected string $jwt;

    protected Response $response;

    /**
     * @throws ValidationError
     */
    public function __construct( $jwt, Response $response ) {

        try {
            $this->jwt = SimpleJWT::getValidPayload( $jwt );
        } catch ( DomainException $e ) {
            throw new ValidationError( $e->getMessage(), $e->getCode(), $e );
        }

        $this->response = $response;

    }

    public function prepareUserInvitedSignUpRedirect() {

        $_SESSION[ 'invited_to_team' ] = $this->jwt;
        FlashMessage::set( 'popup', 'signup', FlashMessage::SERVICE );
        FlashMessage::set( 'signup_email', $this->jwt[ 'email' ], FlashMessage::SERVICE );

    }

    /**
     * @throws ReflectionException
     */
    public static function completeTeamSignUp( $user, $invitation ) {

        $teamStruct = ( new TeamDao )->findById( $invitation[ 'team_id' ] );

        $teamModel = new TeamModel( $teamStruct );
        $teamModel->setUser( $user );
        $teamModel->addMemberEmail( $invitation[ 'email' ] );
        $teamModel->updateMembers();

        $pendingInvitation = new PendingInvitations( ( new RedisHandler() )->getConnection(), $invitation );
        $pendingInvitation->remove(); // remove pending invitation

        unset( $_SESSION[ 'invited_to_team' ] );

    }

    /**
     * @throws ReflectionException
     */
    public static function hasPendingInvitations() {

        if ( !isset( $_SESSION[ 'invited_to_team' ] ) || empty( $_SESSION[ 'invited_to_team' ][ 'team_id' ] ) ) { // check if this is the right session caller
            return false;
        }

        $pendingInvitation = new PendingInvitations( ( new RedisHandler() )->getConnection(), $_SESSION[ 'invited_to_team' ] );
        if ( !$pendingInvitation->hasPengingInvitation( $_SESSION[ 'invited_to_team' ][ 'team_id' ] ) ) {
            return false; // pending invitation already accepted (one-time token consumed)
        }

        return true;

    }

}