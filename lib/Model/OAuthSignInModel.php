<?php

use Controller\Abstracts\Authentication\AuthCookie;
use Controller\Abstracts\Authentication\AuthenticationHelper;
use Email\WelcomeEmail;
use Utils\ConnectedServices\OauthTokenEncryption;
use Teams\TeamDao;
use Users\MetadataDao;
use Users\RedeemableProject;

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/02/2018
 * Time: 17:34
 */
class OAuthSignInModel {

    protected $user;
    protected $profilePictureUrl;
    protected $provider;

    public function __construct( $firstName, $lastName, $email ) {
        if ( empty( $firstName ) ) {
            $firstName = "Anonymous";
        }

        if ( empty( $lastName ) ) {
            $lastName = "User";
        }

        $this->user = new Users_UserStruct( [
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => $email
        ] );

    }

    public function setAccessToken( $token ) {
        $this->user->oauth_access_token = OauthTokenEncryption::getInstance()->encrypt(
                json_encode( $token )
        );
    }

    public function getUser() {
        return $this->user;
    }

    /**
     * @throws ReflectionException
     */
    public function signIn(): bool {
        $userDao      = new Users_UserDao();
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

        if ( !is_null( $this->provider ) ) {
            $this->_updateProvider();
        }

        $this->_authenticateUser();

        $project = new RedeemableProject( $this->user, $_SESSION );
        $project->tryToRedeem();

        return true;
    }

    protected function _updateProfilePicture() {
        $dao = new MetadataDao();
        $dao->set( $this->user->uid, $this->provider . '_picture', $this->profilePictureUrl );
    }

    public function setProfilePicture( $pictureUrl ) {
        $this->profilePictureUrl = $pictureUrl;
    }

    protected function _updateProvider() {
        $dao = new MetadataDao();
        $dao->set( $this->user->uid, 'oauth_provider', $this->provider );
    }

    public function setProvider( $provider ) {
        $this->provider = $provider;
    }

    protected function _createNewUser() {
        $this->user->create_date = Utils::mysqlTimestamp( time() );
        $this->user->uid         = Users_UserDao::insertStruct( $this->user );

        $dao = new TeamDao();
        $dao->getDatabaseHandler()->begin();
        $dao->createPersonalTeam( $this->user );
        $dao->getDatabaseHandler()->commit();
    }

    protected function _updateExistingUser( Users_UserStruct $existing_user ) {
        $this->user->uid = $existing_user->uid;
        Users_UserDao::updateStruct( $this->user, [
                'fields' =>
                        [ 'oauth_access_token' ]
        ] );
    }

    /**
     * @throws ReflectionException
     */
    protected function _authenticateUser() {
        AuthCookie::setCredentials( $this->user );
        AuthenticationHelper::getInstance( $_SESSION );
    }

    protected function _welcomeNewUser() {
        $email = new WelcomeEmail( $this->user );
        $email->send();
        FlashMessage::set( 'popup', 'profile', FlashMessage::SERVICE );
    }


}