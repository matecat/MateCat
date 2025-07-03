<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/02/2017
 * Time: 12:56
 */

namespace View\API\V2\Json;


use Model\Users\UserStruct;

class User {
    public static function renderItem( UserStruct $user ) {
        return [
                'uid'          => (int)$user->uid,
                'first_name'   => $user->first_name,
                'last_name'    => $user->last_name,
                'email'        => $user->email,
                'has_password' => !is_null( $user->pass )
        ];
    }

    public static function renderItemPublic( UserStruct $user ) {
        return [
                'uid'        => (int)$user->uid,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
        ];
    }

}