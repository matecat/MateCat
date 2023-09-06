<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/02/2017
 * Time: 12:56
 */

namespace API\V2\Json;


use Users_UserStruct;

class User
{
    public static function renderItem( Users_UserStruct $user ) {
        return array(
            'uid'           => (int) $user->uid,
            'first_name'    => $user->first_name,
            'last_name'     => $user->last_name,
            'email'         => $user->email,
            'has_password'  => !is_null($user->pass)
        );
    }

    public static function renderItemPublic( Users_UserStruct $user ){
        return array(
                'uid'           => (int) $user->uid,
                'first_name'    => $user->first_name,
                'last_name'     => $user->last_name,
        );
    }

}