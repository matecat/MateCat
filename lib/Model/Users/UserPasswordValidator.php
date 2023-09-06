<?php

namespace Users;

use Exceptions\ValidationError;

class UserPasswordValidator {

    /**
     * @throws ValidationError
     */
    public static function validatePassword( $password, $password_confirmation ) {

        if ( strlen( $password ) < 12 ) {
            throw new ValidationError( 'Password must be at least 12 characters' );
        }

        if ( $password !== $password_confirmation ) {
            throw new ValidationError( 'Passwords must match' );
        }

        if ( !preg_match( '/[ !"#$%&\'()*+,-.\/:;<=>?@\[\]^_`{|}~]/', $password ) ) {
            throw new ValidationError( 'Passwords must contain at least one special character: !"#\$%&\'()\*\+,-./:;<=>?@[]^_`{|}~' );
        }

    }

}