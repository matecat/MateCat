<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 11/04/16
 * Time: 19.52
 *
 */


class SimpleHashTest extends PHPUnit_Framework_TestCase
{
    public function testWrongInitialization(){
        $invited_by_uid   = 166;
        $email            = "domenico.1234455@translated.net";
        $request_info     = json_encode( [ "organization_id" => 1 ] );
        $this->setExpectedException( '\UnexpectedValueException' );
        new SimpleJWT( [
                $invited_by_uid,
                $email,
                $request_info,
        ] );
    }

    public function testEncryption(){
        $invited_by_uid   = 166;
        $email            = "domenico.1234455@translated.net";
        $request_info     = json_encode( [ "organization_id" => 1 ] );

        $x = new SimpleJWT( [
                'invited_by_uid' => $invited_by_uid,
                'email'          => $email,
                'request_info'   => $request_info,
        ] );

        $result = $x->encrypt();
        $this->assertTrue( is_array( $result ) );
        $this->assertArrayHasKey( 'signature', $result );
        $this->assertArrayHasKey( 'payload', $result );
        $this->assertArrayHasKey( 'exp', $result['payload'] );
        $this->assertArrayHasKey( 'iat', $result['payload'] );
        $this->assertTrue( $result[ 'payload' ][ 'context' ][ 'invited_by_uid' ] == $invited_by_uid );
        $this->assertTrue( $result[ 'payload' ][ 'context' ][ 'email' ] == $email );
        $this->assertTrue( $result[ 'payload' ][ 'context' ][ 'request_info' ] == $request_info );
    }

    public function testValidateTokenEncryption(){
        $invited_by_uid   = 166;
        $email            = "domenico.1234455@translated.net";
        $request_info     = json_encode( [ "organization_id" => 1 ] );

        $x = new SimpleJWT( [
                'invited_by_uid' => $invited_by_uid,
                'email'          => $email,
                'request_info'   => $request_info,
        ] );

        $result = $x->encrypt();
        $this->assertTrue( $x->validate( $result ) );
    }

    public function testValidateTokenEncryptionByJWT(){
        $invited_by_uid   = 166;
        $email            = "domenico.1234455@translated.net";
        $request_info     = json_encode( [ "organization_id" => 1 ] );

        $x = new SimpleJWT( [
                'invited_by_uid' => $invited_by_uid,
                'email'          => $email,
                'request_info'   => $request_info,
        ] );

        $this->assertTrue( $x->validate( (string)$x ) );
    }


    public function testInvalidToken_tamper_field(){
        $invited_by_uid   = 166;
        $email            = "domenico.1234455@translated.net";
        $request_info     = json_encode( [ "organization_id" => 1 ] );

        $x = new SimpleJWT( [
                'invited_by_uid' => $invited_by_uid,
                'email'          => $email,
                'request_info'   => $request_info,
        ] );

        $result = $x->encrypt();

        //change a value param
        $result[ 'payload' ][ 'invited_by_uid' ] = 123;

        //assert false
        $this->assertFalse( $x->validate( $result ) );
    }

    public function testInvalidToken_tamper_hash(){
        $invited_by_uid   = 166;
        $email            = "domenico.1234455@translated.net";
        $request_info     = json_encode( [ "organization_id" => 1 ] );

        $x = new SimpleJWT( [
                'invited_by_uid' => $invited_by_uid,
                'email'          => $email,
                'request_info'   => $request_info,
        ] );

        $result = $x->encrypt();

        //change a value param
        $result[ 'signature' ] = "376715df7403f293a019fab9d048e2a904216108fc85190dc824d35375f94bc9";

        //assert false
        $this->assertFalse( $x->validate( $result ) );
    }

    public function testArrayAccess(){
        $invited_by_uid   = 166;
        $email            = "domenico.1234455@translated.net";
        $request_info     = json_encode( [ "organization_id" => 1 ] );

        $x = new SimpleJWT( [
                'invited_by_uid' => $invited_by_uid,
                'email'          => $email,
                'request_info'   => $request_info,
        ] );

        $newEmail = "domenico@translated.net";

        //TEST ArrayAccess
        $x[ 'email' ] = $newEmail;
        $x[ 'testAccess' ] = "a new key/value pair";

        $result = $x->encrypt();
        $this->assertTrue( is_array( $result ) );
        $this->assertTrue( $result[ 'payload' ][ 'context' ][ 'invited_by_uid' ] == $invited_by_uid );
        $this->assertTrue( $result[ 'payload' ][ 'context' ][ 'request_info' ] == $request_info );

        
        //TEST ArrayAccess
        $this->assertTrue( $result[ 'payload' ][ 'context' ][ 'email' ] == $newEmail );
        $this->assertFalse( $result[ 'payload' ][ 'context' ][ 'email' ] == $email );
        $this->assertArrayHasKey( 'testAccess', $result[ 'payload' ][ 'context' ] );
        $this->assertEquals( "a new key/value pair", $result[ 'payload' ][ 'context' ][ 'testAccess' ] );


        $this->assertTrue( $x->validate( $result ) );
    }

    public function testAssignmentRuntime(){
        $invited_by_uid   = 166;
        $email            = "domenico.1234455@translated.net";
        $request_info     = [ "organization_id" => 1 ];

        $x = new SimpleJWT();

        //TEST ArrayAccess
        $x[ 'email' ] = $email;
        $x[ 'invited_by_uid' ] = $invited_by_uid;
        $x[ 'request_info' ] = $request_info;
        $x[ 'testAccess' ] = "a new key/value pair";

        $result = $x->encrypt();
        $this->assertTrue( is_array( $result ) );
        $this->assertTrue( $result[ 'payload' ][ 'context' ][ 'invited_by_uid' ] == $invited_by_uid );
        $this->assertTrue( $result[ 'payload' ][ 'context' ][ 'request_info' ] == $request_info );


        //TEST ArrayAccess
        $this->assertEquals( $result[ 'payload' ][ 'context' ][ 'email' ], $email );
        $this->assertArrayHasKey( 'testAccess', $result[ 'payload' ][ 'context' ] );
        $this->assertEquals( "a new key/value pair", $result[ 'payload' ][ 'context' ][ 'testAccess' ] );

        $this->assertTrue( $x->validate( (string)$x ) );
        $this->assertTrue( $x->validate( $result ) );

    }

}