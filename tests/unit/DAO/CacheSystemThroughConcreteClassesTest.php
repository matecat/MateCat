<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 17/04/25
 * Time: 17:55
 *
 */

namespace unit\DAO;

use Database;
use Exception;
use INIT;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use RedisHandler;
use ReflectionException;
use TestHelpers\AbstractTest;
use Utils;

class CacheSystemThroughConcreteClassesTest extends AbstractTest {
    private static string $email;
    private static string $uid;
    private static string $sql_delete_user;

    /**
     * @beforeClass
     * @throws Exception
     */
    public static function init(): void {
        /**
         * user insertion
         */
        self::$email     = Utils::uuid4() . "bar@foo.net";
        $sql_insert_user = "INSERT INTO " . INIT::$DB_DATABASE . ".`users` (`uid`, `email`, `salt`, `pass`, `create_date`, `first_name`, `last_name` ) VALUES (NULL, '" . self::$email . "', '12345', '987654321qwerty', '2016-04-11 13:41:54', 'Bar', 'Foo' );";
        Database::obtain()->getConnection()->query( $sql_insert_user );
        self::$uid = Database::obtain()->getConnection()->lastInsertId();

        self::$sql_delete_user = "DELETE FROM " . INIT::$DB_DATABASE . ".`users` WHERE uid='" . self::$uid . "';";

    }

    /**
     * @afterClass
     * @throws ReflectionException
     */
    public static function clean(): void {
        Database::obtain()->getConnection()->query( self::$sql_delete_user );
        $client = ( new RedisHandler )->getConnection();
        $client->flushdb();
    }

    /**
     * @test
     * @return void
     * @throws ReflectionException
     */
    public function test_shouldSetAndGetFromCache() {

        $client = ( new RedisHandler )->getConnection();
        $map    = $client->hgetall( "UserDao::getByUid-" . self::$uid );
        $this->assertEmpty( $map );

        $underTest = new UserDao();

        $underTest->setCacheTTL( 600 );

        $user = $underTest->getByUid( self::$uid );

        $this->assertTrue( $user instanceof UserStruct );
        $this->assertEquals( self::$uid, $user->uid );
        $this->assertEquals( self::$email, $user->email );
        $this->assertEquals( "12345", $user->salt );
        $this->assertEquals( "987654321qwerty", $user->pass );
        $this->assertEquals( "2016-04-11 13:41:54", $user->create_date );
        $this->assertEquals( "Bar", $user->first_name );
        $this->assertEquals( "Foo", $user->last_name );

        $client = ( new RedisHandler )->getConnection();
        $map    = $client->hgetall( "UserDao::getByUid-" . self::$uid );
        $this->assertNotEmpty( $map );
        $this->assertTrue( is_array( $map ) );

        $value = array_values( $map )[ 0 ];
        $this->assertEquals( serialize( [ $user ] ), $value );

        $key    = array_keys( $map )[ 0 ];
        $keyMap = $client->get( $key );
        $this->assertEquals( "UserDao::getByUid-" . self::$uid, $keyMap );

    }

    /**
     * @test
     * @depends test_shouldSetAndGetFromCache
     * @throws ReflectionException
     */
    public function test_shouldDestroyCache() {

        $client = ( new RedisHandler )->getConnection();
        $map    = $client->hgetall( "UserDao::getByUid-" . self::$uid );
        $this->assertNotEmpty( $map );

        $key    = array_keys( $map )[ 0 ];
        $keyMap = $client->get( $key );
        $this->assertEquals( "UserDao::getByUid-" . self::$uid, $keyMap );


        $underTest = new UserDao();
        $underTest->destroyCacheByUid( self::$uid );


        $map = $client->hgetall( "UserDao::getByUid-" . self::$uid );
        $this->assertEmpty( $map );

    }

}