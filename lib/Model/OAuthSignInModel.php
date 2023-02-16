<?php

use Email\WelcomeEmail;
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

    protected $user ;
    protected $profilePictureUrl ;

    public function __construct( $firstName, $lastName, $email ) {
        if ( empty($firstName) ) {
            $firstName = "Anonymous";
        }

        if ( empty($lastName) ) {
            $lastName = "User";
        }

        $this->user = new Users_UserStruct( [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email
        ] );

    }

    public function setAccessToken( $token ) {
        $this->user->oauth_access_token = OauthTokenEncryption::getInstance()->encrypt(
                json_encode( $token )
        );
    }

    public function getUser() {
        return $this->user ;
    }

    public function signIn() {
        $userDao = new Users_UserDao() ;
        $existingUser = $userDao->getByEmail( $this->user->email ) ;

        if ( $existingUser ) {
            $welcome_new_user = !$existingUser->everSignedIn();
            $this->_updateExistingUser($existingUser) ;

        } else {
            $welcome_new_user = true ;
            $this->_createNewUser();
        }

        if ( $welcome_new_user ) {
            $this->_welcomeNewUser();
        }

        $this->_authenticateUser();

        if ( !is_null( $this->profilePictureUrl ) ) {
            $this->_updateProfilePicture() ;
        }

        $project = new RedeemableProject($this->user, $_SESSION)  ;
        $project->tryToRedeem()  ;

        return true ;
    }

    protected function _updateProfilePicture() {
        $dao = new MetadataDao();
        $dao->set($this->user->uid, 'gplus_picture', $this->profilePictureUrl );
    }

    public function setProfilePicture( $pictureUrl ) {
        $this->profilePictureUrl = $pictureUrl ;
    }

    protected function _createNewUser() {
        $this->user->create_date = Utils::mysqlTimestamp(time() ) ;
        $this->user->uid = Users_UserDao::insertStruct($this->user);

        $dao = new TeamDao();
        $dao->getDatabaseHandler()->begin();
        $dao->createPersonalTeam($this->user);
        $dao->getDatabaseHandler()->commit();
    }

    protected function _updateExistingUser(Users_UserStruct $existing_user) {
        $this->user->uid = $existing_user->uid ;
        Users_UserDao::updateStruct( $this->user, array('fields' =>
                                                                array('oauth_access_token')
        ) ) ;
    }

    protected function _authenticateUser() {
        AuthCookie::setCredentials($this->user->email, $this->user->uid );
        $_SESSION[ 'cid' ]  = $this->user->email ;
        $_SESSION[ 'uid' ]  = $this->user->uid ;
    }

    protected function _welcomeNewUser() {
        $email = new WelcomeEmail($this->user) ;
        $email->send() ;
        FlashMessage::set('popup', 'profile', FlashMessage::SERVICE);
    }





}