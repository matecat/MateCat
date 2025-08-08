<?php

namespace Model\Users\Authentication;

use Controller\Abstracts\Authentication\AuthCookie;
use Controller\Abstracts\Authentication\AuthenticationHelper;
use Controller\Abstracts\Authentication\SessionTokenRingHandler;
use Controller\Abstracts\FlashMessage;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Exception;
use Model\ConnectedServices\Oauth\OauthTokenEncryption;
use Model\Teams\TeamDao;
use Model\Users\MetadataDao;
use Model\Users\RedeemableProject;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use ReflectionException;
use Utils\Email\WelcomeEmail;
use Utils\Tools\Utils;

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/02/2018
 * Time: 17:34
 */
class OAuthSignInModel {

    protected UserStruct $user;
    protected ?string    $profilePictureUrl = null;
    protected string     $provider;

    public function __construct( string $email, ?string $firstName = null, ?string $lastName = null ) {
        if ( empty( $firstName ) ) {
            $firstName = "Anonymous";
        }

        if ( empty( $lastName ) ) {
            $lastName = "User";
        }

        $this->user = new UserStruct( [
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => $email
        ] );

    }

    /**
     * @param string $token
     *
     * @throws EnvironmentIsBrokenException
     * @throws Exception
     */
    public function setAccessToken( string $token ) {
        $this->user->oauth_access_token = OauthTokenEncryption::getInstance()->encrypt(
                json_encode( $token )
        );
    }

    public function getUser(): UserStruct {
        return $this->user;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function signIn(): bool {
        $userDao      = new UserDao();
        $existingUser = $userDao->getByEmail( $this->user->email );

        if ( $existingUser ) {
            $welcome_new_user = !$existingUser->everSignedIn();
            $this->_updateExistingUser( $existingUser );

        } else {
            $welcome_new_user = true;
            $this->_createNewUser();
        }

        if ( $welcome_new_user ) {
            $this->_welcomeNewUser();
        }

        if ( !is_null( $this->profilePictureUrl ) ) {
            $this->_updateProfilePicture();
        }

        $this->_updateProvider();
        $this->_authenticateUser();

        $project = new RedeemableProject( $this->user, $_SESSION );
        $project->tryToRedeem();

        return true;
    }

    /**
     * @throws ReflectionException
     */
    protected function _updateProfilePicture() {
        $dao = new MetadataDao();
        $dao->set( $this->user->uid, $this->provider . '_picture', $this->profilePictureUrl );
    }

    public function setProfilePicture( ?string $pictureUrl = null ) {
        $this->profilePictureUrl = $pictureUrl;
    }

    /**
     * @throws ReflectionException
     */
    protected function _updateProvider() {
        $dao = new MetadataDao();
        $dao->set( $this->user->uid, 'oauth_provider', $this->provider );
    }

    public function setProvider( string $provider ) {
        $this->provider = $provider;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    protected function _createNewUser() {
        $this->user->create_date = Utils::mysqlTimestamp( time() );
        $this->user->uid         = UserDao::insertStruct( $this->user );

        $dao = new TeamDao();
        $dao->getDatabaseHandler()->begin();
        $dao->createPersonalTeam( $this->user );
        $dao->getDatabaseHandler()->commit();
    }

    /**
     * @throws Exception
     */
    protected function _updateExistingUser( UserStruct $existing_user ) {
        $this->user->uid = $existing_user->uid;
        UserDao::updateStruct( $this->user, [
                'fields' =>
                        [ 'oauth_access_token' ]
        ] );
    }

    /**
     */
    protected function _authenticateUser() {
        AuthCookie::setCredentials( $this->user, new SessionTokenRingHandler() );
        AuthenticationHelper::getInstance( $_SESSION );
    }

    /**
     * @throws Exception
     */
    protected function _welcomeNewUser() {
        $email = new WelcomeEmail( $this->user );
        $email->send();
        FlashMessage::set( 'popup', 'profile', FlashMessage::SERVICE );
    }


}