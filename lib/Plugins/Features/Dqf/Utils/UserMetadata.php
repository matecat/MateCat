<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 03/03/2017
 * Time: 17:09
 */

namespace Features\Dqf\Utils;


use Users\MetadataDao;
use Users_UserStruct;

class UserMetadata {

    const DQF_USERNAME_KEY = 'dqf_username' ;
    const DQF_PASSWORD_KEY = 'dqf_password' ;

    public static function extractCredentials( $user_metadata ) {
        return array( $user_metadata[ self::DQF_USERNAME_KEY ], $user_metadata[ self::DQF_PASSWORD_KEY ] );
    }

    public static function clearCredentials( Users_UserStruct $user ) {
        $dao = new MetadataDao() ;
        $dao->delete( $user->uid, self::DQF_USERNAME_KEY ) ;
        $dao->delete( $user->uid, self::DQF_PASSWORD_KEY ) ;
    }


}