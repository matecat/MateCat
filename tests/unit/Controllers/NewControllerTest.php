<?php

namespace unit\Controllers;

use Controller\API\V1\NewController;
use Exception;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use TestHelpers\AbstractTest;
use Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException;

class NewControllerTest extends AbstractTest {
    private NewController    $controller;
    private Request          $requestMock;
    private Response         $responseMock;
    private ReflectionMethod $method;
    /**
     * @var UserStruct
     */
    private UserStruct $user;

    public function setUp(): void {
        parent::setUp();
        $this->requestMock  = $this->createMock( Request::class );
        $this->responseMock = $this->createMock( Response::class );
        $this->user         = $this->createMock( UserStruct::class );
    }

    /**
     * @throws Exception
     */
    public function createMocks(): void {
        $this->controller = new NewController( $this->requestMock, $this->responseMock, null, null );
        $reflector        = new ReflectionClass( $this->controller );
        $this->method     = $reflector->getMethod( 'validateTheRequest' );

        $reflector = new ReflectionProperty( $this->controller, 'user' );
        $reflector->setValue( $this->controller, $this->user );
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testValidateTheRequestWithValidParameters() {

        $this->user->expects( $this->any() )->method( 'getPersonalTeam' )->willReturn( new TeamStruct() );

        $this->requestMock = new Request(
                [],
                [
                        'character_counter_count_tags' => '1',
                        'character_counter_mode'       => 'google_ads',
                        'due_date'                     => '20251231',
                        'source_lang'                  => 'en',
                        'target_lang'                  => 'fr,de',
                        'mt_engine'                    => 1,
                        'tms_engine'                   => 1,
                        'segmentation_rule'            => 'patent',
                ],
                [],
                [],
                [
                        'file[]' => [
                                'name'     => 'foo.docx',
                                'tmp_name' => '/tmp/xdwlky',
                        ]
                ]
        );

        $this->createMocks();

        $validateParameters = $this->method->invoke( $this->controller );

        $this->assertIsArray( $validateParameters );
        $this->assertArrayHasKey( 'source_lang', $validateParameters );
        $this->assertEquals( 'en-US', $validateParameters[ 'source_lang' ] );
        $this->assertEquals( 'foo', $validateParameters[ 'project_name' ] );
    }

    /**
     * @throws Exception
     */
    public function testValidateTheRequestWithMissingFile() {
        $this->requestMock = new Request(
                [],
                [
                        'character_counter_count_tags' => '1',
                        'character_counter_mode'       => 'google_ads',
                        'due_date'                     => '20251231',
                        'source_lang'                  => 'en',
                        'target_lang'                  => 'fr,de',
                        'mt_engine'                    => 1,
                        'tms_engine'                   => 1,
                        'segmentation_rule'            => 'patent',
                ]
        );
        $this->createMocks();

        $this->expectException( InvalidArgumentException::class );
        $this->expectExceptionMessage( 'Missing file. Not Sent.' );
        $this->method->invoke( $this->controller );

    }

    /**
     * @throws Exception
     */
    public function testValidateTheRequestWithInvalidParameters() {

        $this->requestMock = new Request(
                [],
                [
                        'character_counter_count_tags' => '1',
                        'character_counter_mode'       => 'google_ads',
                        'due_date'                     => '20251231',
                        'source_lang'                  => 'en',
                        'target_lang'                  => 'fr,de',
                        'mt_engine'                    => 1,
                        'tms_engine'                   => 5,
                        'segmentation_rule'            => 'patent',
                ],
                [],
                [],
                [ 'file[]' => [ 'name' => 'foo.docx' ] ]
        );
        $this->createMocks();

        $this->expectException( InvalidArgumentException::class );
        $this->expectExceptionMessage( 'Invalid TM Engine.' );
        $this->method->invoke( $this->controller );
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testValidateTheRequestWithInvalidSourceLang() {
        $this->requestMock = new Request(
                [],
                [
                        'character_counter_count_tags' => '1',
                        'character_counter_mode'       => 'google_ads',
                        'due_date'                     => '20251231',
                        'source_lang'                  => 'zz',
                        'target_lang'                  => 'fr,de',
                        'mt_engine'                    => 1,
                        'tms_engine'                   => 5,
                        'segmentation_rule'            => 'patent',
                ],
                [],
                [],
                [ 'file[]' => [ 'name' => 'foo.docx' ] ]
        );
        $this->createMocks();

        $this->expectException( InvalidArgumentException::class );
        $this->expectExceptionMessage( 'Missing source language.' );
        $this->method->invoke( $this->controller );
    }


    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testValidateSubfilteringOptionsReturnsNullForNone(): void {
        $controller = new NewController(
                new Request(),
                new Response(),
        );

        $ref = new ReflectionClass( $controller );
        $m   = $ref->getMethod( 'validateSubfilteringOptions' );

        $this->assertNull( $m->invoke( $controller, 'none' ) );
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testValidateSubfilteringOptionsReturnsNullForEmptyString(): void {
        $controller = new NewController(
                new Request(),
                new Response()
        );

        $ref = new ReflectionClass( $controller );
        $m   = $ref->getMethod( 'validateSubfilteringOptions' );

        $this->assertNull( $m->invoke( $controller, '' ) );
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testValidateSubfilteringOptionsReturnsArrayForValidJson(): void {
        $controller = new NewController(
                new Request(),
                new Response()
        );

        $ref = new ReflectionClass( $controller );
        $m   = $ref->getMethod( 'validateSubfilteringOptions' );

        $result = $m->invoke( $controller, '["twig","markup"]' );
        $this->assertIsArray( $result );
        $this->assertSame( [ 'twig', 'markup' ], $result );
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testValidateSubfilteringOptionsReturnsEmptyArrayForEmptyJsonArray(): void {
        $controller = new NewController(
                new Request(),
                new Response()
        );

        $ref = new ReflectionClass( $controller );
        $m   = $ref->getMethod( 'validateSubfilteringOptions' );

        $result = $m->invoke( $controller, '[]' );
        $this->assertIsArray( $result );
        $this->assertSame( [], $result );
    }


    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testValidateSubfilteringOptionsThrowsForMalformedJson(): void {
        $controller = new NewController(
                new Request(),
                new Response()
        );

        $ref = new ReflectionClass( $controller );
        $m   = $ref->getMethod( 'validateSubfilteringOptions' );

        $this->expectException( JsonValidatorGenericException::class );
        $m->invoke( $controller, 'not-a-json' );
    }


}