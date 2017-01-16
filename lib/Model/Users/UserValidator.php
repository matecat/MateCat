<?php


class Users_UserValidator extends DataAccess_AbstractValidator  {

    public static function validatePassword( $password ) {
        if ( strlen( $password ) < 8 ) {
            throw new \Exceptions\ValidationError('Password must be at least 8 characters');
        }
    }

    public function validate() {
        // TODO
    }


}