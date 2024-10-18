<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 05/09/23
 * Time: 17:11
 *
 */

namespace Users\Authentication;

use API\Commons\Exceptions\ValidationError;
use Exception;
use Users_UserDao;
use Users_UserStruct;
use Utils;

class ChangePasswordModel {

    /**
     * @var Users_UserStruct
     */
    private $user;

    public function __construct( Users_UserStruct $user ) {
        $this->user = $user;
    }

    /**
     * @param $old_password
     * @param $new_password
     * @param $new_password_confirmation
     *
     * @throws ValidationError
     * @throws Exception
     */
    public function changePassword( $old_password, $new_password, $new_password_confirmation ) {

        if ( !Utils::verifyPass( $old_password, $this->user->salt, $this->user->pass ) ) {
            throw new ValidationError( "Invalid password" );
        }

        if ( $old_password === $new_password ) {
            throw new ValidationError( "New password cannot be the same as your old password" );
        }

        UserPasswordValidator::validatePassword( $new_password, $new_password_confirmation );

        $this->user->pass = Utils::encryptPass( $new_password, $this->user->salt );

        $fieldsToUpdate = [
                'fields' => [ 'pass' ]
        ];

        // update email_confirmed_at only if it's null
        if ( null === $this->user->email_confirmed_at ) {
            $this->user->email_confirmed_at = date( 'Y-m-d H:i:s' );
            $fieldsToUpdate[ 'fields' ][]   = 'email_confirmed_at';
        }

        Users_UserDao::updateStruct( $this->user, $fieldsToUpdate );
        ( new Users_UserDao )->destroyCacheByEmail( $this->user->email );
        ( new Users_UserDao )->destroyCacheByUid( $this->user->uid );

    }

}