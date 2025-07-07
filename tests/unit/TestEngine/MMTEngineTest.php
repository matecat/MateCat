<?php

use Model\Database;
use Model\Engines\EngineStruct;
use TestHelpers\AbstractTest;
use TestHelpers\InvocationInspector;

/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 19/08/24
 * Time: 18:47
 *
 */
class MMTEngineTest extends AbstractTest {

    protected int       $engine_id           = -1;
    protected int       $not_valid_engine_id = -1;
    protected ?Database $database_instance   = null;

    public function setUp(): void {

        parent::setUp();

        $this->database_instance = Database::obtain();

        /**
         * engine insertion
         */
        $sql_insert_engine_valid = <<<H
INSERT INTO engines (name, type, description, base_url, translate_relative_url, contribute_relative_url, update_relative_url, delete_relative_url, others, class_load, extra_parameters, google_api_compliant_version, penalty, active, uid) VALUES ('ModernMT Full', 'MT', 'ModernMT for subscribers', 'http://MMT', 'translate', 'memories/content', 'memories/content', null, '{"tmx_import_relative_url":"memories\\/content","api_key_check_auth_url":"users\\/me","user_update_activate":"memories\\/connect","context_get":"context-vector"}', 'MMT', '{"MMT-License":"XXXXX","MMT-pretranslate":true,"MMT-preimport":false,"MMT-context-analyzer":false}', '2', 14, 1, 1886428310);
H;

        $sql_insert_engine_NOT_valid = <<<H
INSERT INTO engines (name, type, description, base_url, translate_relative_url, contribute_relative_url, update_relative_url, delete_relative_url, others, class_load, extra_parameters, google_api_compliant_version, penalty, active, uid) VALUES ('WRONG', 'TM', 'ModernMT wrong', 'http://MMT', 'translate', 'memories/content', 'memories/content', null, '', 'MMT', '', '2', 14, 1, 1886428310);
H;

        $this->database_instance->getConnection()->query( $sql_insert_engine_valid );
        $this->engine_id = $this->database_instance->getConnection()->lastInsertId();
        $this->database_instance->getConnection()->query( $sql_insert_engine_NOT_valid );
        $this->not_valid_engine_id = $this->database_instance->getConnection()->lastInsertId();

    }

    public function tearDown(): void {

        $this->database_instance->getConnection()->query( "DELETE FROM engines WHERE id=" . $this->engine_id . ";" );
        $this->database_instance->getConnection()->query( "DELETE FROM engines WHERE id=" . $this->not_valid_engine_id . ";" );
        $flusher = new Predis\Client( INIT::$REDIS_SERVERS );
        $flusher->select( INIT::$INSTANCE_ID );
        $flusher->flushdb();
        parent::tearDown();

    }

    /**
     * @test
     * @throws Exception
     */
    public function constructor_should_raise_exception_when_is_not_an_MT_engine() {

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( "Engine $this->not_valid_engine_id is not a MT engine, found TM -> MMT" );
        Engine::getInstance( $this->not_valid_engine_id );

    }

    /**
     * @test
     */
    public function should_invoke_update_on_client() {

        $stmt = $this->database_instance->getConnection()->prepare( "select * from engines where id = :id" );
        $stmt->execute( [ 'id' => $this->engine_id ] );
        $record    = $stmt->fetch( PDO::FETCH_ASSOC );
        $mmtClient = @$this->getMockBuilder( '\Engines\MMT\MMTServiceApi' )->disableOriginalConstructor()->getMock();
        $mmtClient->expects( $invocation = $this->once() )->method( 'updateMemoryContent' );

        $mmtEngine = @$this->getMockBuilder( '\Utils\Engines\MMT' )
                ->setConstructorArgs( [ new EngineStruct( $record ) ] )
                ->onlyMethods( [ '_getClient' ] )->getMock();

        $mmtEngine->expects( $this->once() )
                ->method( '_getClient' )
                ->willReturn( $mmtClient );

        $result = $mmtEngine->update( [
                'tuid'        => 'xx',
                'keys'        => [ 'k1', 'k2' ],
                'source'      => '_source',
                'target'      => '_target',
                'segment'     => '_segment',
                'translation' => '_translation',
                'session'     => '_session',
        ] );

        $inspector = new InvocationInspector( $invocation );

        $this->assertTrue( $result );
        $this->assertTrue( !empty( $invocation ) );
        $this->assertEquals( 'xx', $inspector->getInvocations()[ 0 ]->getParameters()[ 0 ] );
        $this->assertEquals( [ 'x_mm-k1', 'x_mm-k2' ], $inspector->getInvocations()[ 0 ]->getParameters()[ 1 ] );
        $this->assertEquals( '_source', $inspector->getInvocations()[ 0 ]->getParameters()[ 2 ] );
        $this->assertEquals( '_target', $inspector->getInvocations()[ 0 ]->getParameters()[ 3 ] );
        $this->assertEquals( '_segment', $inspector->getInvocations()[ 0 ]->getParameters()[ 4 ] );
        $this->assertEquals( '_translation', $inspector->getInvocations()[ 0 ]->getParameters()[ 5 ] );
        $this->assertEquals( '_session', $inspector->getInvocations()[ 0 ]->getParameters()[ 6 ] );

    }

}