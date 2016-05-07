<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 04/03/14
 * Time: 13.21
 *
 */

/**
 * Description of QATest
 *
 * @author domenico
 */

class Tests_MemcacheHandlerTest extends AbstractTest {

    public function setUp() {
        parent::setUp();
        MemcacheHandler::getInstance( array( '127.0.0.1:11211' => 1 ) )->flush();
    }

    public function tearDown() {
        parent::tearDown();
        MemcacheHandler::close();
    }

    public function testEmptyConfiguration(){
        INIT::$MEMCACHE_SERVERS = array();
        MemcacheHandler::close();
        $this->setExpectedException( 'LogicException' );
        /**
         * without INIT configuration
         */
        $x = MemcacheHandler::getInstance();
    }

    public function testConnection(){

        $serverPool = array( '127.0.0.1:11211' => 1, 'localhost:11211' => 1 );

        $x = MemcacheHandler::getInstance( $serverPool );
        $this->assertEmpty( $x->get( 'x' ) );

    }

    public function testConnectionFailure(){

        $serverPool = array( '127.0.0.1:11211' => 1, 'localhost:11212' => 1 );

        $x = MemcacheHandler::getInstance( $serverPool );

        //Notice:  MemcachePool::get(): Server 127.0.0.1 (tcp 11212, udp 0) failed with: Connection refused (111)
        $this->assertEmpty( $x->get( 'x' ) );

        $this->assertEquals( 1 , count( $x->getCurrentPool() ) );

    }

    public function testAllServersDown(){
        $serverPool = array( '127.0.0.1:11213' => 1, 'localhost:11212' => 1 );

        $x = MemcacheHandler::getInstance( $serverPool );

        //Notice:  MemcachePool::get(): Server 127.0.0.1 (tcp 11212, udp 0) failed with: Connection refused (111)
        $this->assertEmpty( $x->get( 'x' ) );

        $this->assertEquals( 1 , count( $x->getCurrentPool() ) );

    }

    public function testSet(){

        $serverPool = array( '127.0.0.1:11211' => 1, 'localhost:11211' => 1 );
        $x = MemcacheHandler::getInstance( $serverPool );
        $this->assertTrue( $x->set( 'x', 123, 1 ) );
        $this->assertEquals( 123, $x->get('x') );
        usleep( 1010000 ); //sleep more than ttl
        $this->assertEmpty( $x->get('x') );

    }

    public function testIncrement(){

        $serverPool = array( '127.0.0.1:11211' => 1, 'localhost:11211' => 1 );
        $x = MemcacheHandler::getInstance( $serverPool );
        $this->assertEquals( 123, $x->increment( 'x', 123 ) );
        $this->assertEquals( 246, $x->increment( 'x', 123 ) );
        $this->assertEquals( 246, $x->get('x') );
        $this->assertEquals( 245, $x->decrement( 'x' ) );
        $this->assertTrue( $x->delete( 'x' ) );
        $this->assertEquals( 0, $x->get('x') );

    }

    public function testDecrement(){
        $serverPool = array( '127.0.0.1:11211' => 1, 'localhost:11211' => 1 );
        $x = MemcacheHandler::getInstance( $serverPool );

        $this->assertEquals( 3, $x->increment( 'x', 3 ) );
        $this->assertEquals( 1, $x->decrement( 'x', 2 ) );
        $this->assertEquals( 0, $x->decrement( 'x' ) );
        $this->assertEquals( 0, $x->decrement( 'x' ) );
        $this->assertEquals( 0, $x->decrement( 'x' ) );

    }

    public function testZeroIncrement(){
        $serverPool = array( '127.0.0.1:11211' => 1, 'localhost:11211' => 1 );
        $x = MemcacheHandler::getInstance( $serverPool );

        $this->assertEquals( 0, $x->increment( 'x', 0 ) );
        $this->assertEquals( 1, $x->increment( 'x', 1 ) );
        $this->assertEquals( 0, $x->decrement( 'x', 2 ) );
        $this->assertEquals( 0, $x->decrement( 'x' ) );
        $this->assertEquals( 0, $x->decrement( 'x' ) );
        $this->assertEquals( 0, $x->decrement( 'x' ) );
    }

}
