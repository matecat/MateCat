<?php

namespace Matecat\TestHelpers\Factory;

use Model\DataAccess\Database;
use Model\Teams\TeamDao;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use Utils\Constants\Teams;

class User extends Base
{

    static function create($values = [])
    {
        $userStruct = static::getNewUser($values);

        $dao = new UserDao(obtainTestDatabase());
        $user = $dao->createUser($userStruct);

        $orgDao = new TeamDao(obtainTestDatabase());
        $orgDao->createUserTeam($user, [
            'type' => Teams::PERSONAL,
            'name' => 'personal'
        ]);

        return $user;
    }

    public static function getNewUser($values = [])
    {
        $values = array_merge([
            'email' => "test-email-" . uniqid('', true) . "@example.org",
            'salt' => '1234abcd',
            'pass' => '1234abcd',
            'first_name' => 'John',
            'last_name' => 'Connor',
            'api_key' => '1234abcd'
        ], $values);

        return new UserStruct($values);
    }

}
