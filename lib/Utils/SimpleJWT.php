<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 16/02/17
 * Time: 20.12
 *
 */
class SimpleJWT implements ArrayAccess {

    protected $storage    = [ 'header' => null, 'payload' => [ 'exp' => null, 'context' => null ], 'signature' => null ];
    protected $secretKey;
    protected $timeToLive = 86400;

    /**
     * SimpleJWT constructor.
     *
     * @param array $hashMap
     * @throws UnexpectedValueException
     */
    public function __construct( Array $hashMap = [] ) {

        if ( [] !== $hashMap && array_keys( $hashMap ) === range( 0, count( $hashMap ) - 1 ) ) {
            throw new UnexpectedValueException( 'Provided Array is not associative.' );
        }

        $this->storage[ 'payload' ][ 'context' ] = $hashMap;
        $this->secretKey = INIT::$AUTHSECRET;

    }

    /**
     * @return array
     */
    public function encrypt() {
        $expire_date = time() + $this->timeToLive;
        $_storage = $this->storage;
        $_storage[ 'header' ] = [ "alg" => "HS256", "typ" => "JWT" ];
        $_storage[ 'payload' ][ 'exp' ] = $expire_date;
        $_storage[ 'payload' ][ 'iss' ] = INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER;
        $_storage[ 'payload' ][ 'iat' ] = time();
        $_hash = hash_hmac( 'sha256', self::base64url_encode( json_encode( $_storage[ 'header' ] ) ) . "." . self::base64url_encode( json_encode( $_storage[ 'payload' ] ) ), $this->secretKey, true );
        $_storage[ 'signature' ] = self::base64url_encode( $_hash );
        return $_storage;
    }

    /**
     * @param $_storage
     *
     * @return bool
     */
    public function validate( $_storage ) {

        if( is_string( $_storage ) ){
            list( $header, $payload, $signature ) = explode( ".", $_storage );
            $_storage = [
                'header' => json_decode( self::base64url_decode( $header ), true ),
                'payload' => json_decode( self::base64url_decode( $payload ) , true ),
                'signature' => $signature
            ];
        }

        $data_hash = $_storage[ 'signature' ];

        $expected_hash = hash_hmac( 'sha256', self::base64url_encode( json_encode( $_storage[ 'header' ] ) ) . "." . self::base64url_encode( json_encode( $_storage[ 'payload' ] ) ), $this->secretKey, true );
        //check if valid hash and expiration still in time
        if ( $data_hash == self::base64url_encode( $expected_hash ) && time() < $_storage[ 'payload' ][ 'exp' ] && $_storage[ 'payload' ][ 'iat' ] > strtotime( "-1 day" ) ) {
            return true;
        }

        return false;

    }

    /**
     * @param mixed $secretKey
     *
     * @return $this
     */
    public function setSecretKey( $secretKey ) {
        $this->secretKey = $secretKey;

        return $this;
    }

    /**
     * @param int $timeToLive
     *
     * @return $this
     * @throws UnexpectedValueException
     */
    public function setTimeToLive( $timeToLive ) {

        if ( !is_numeric( $timeToLive ) || $timeToLive < 0 ) {
            throw new UnexpectedValueException( 'Time To Live must be a positive integer' );
        }

        $this->timeToLive = $timeToLive;

        return $this;
    }

    public function offsetExists( $offset ) {
        return isset( $this->storage[ 'payload' ][ 'context' ][ $offset ] );
    }

    public function offsetGet( $offset ) {
        if ( isset( $this->storage[ 'payload' ][ 'context' ][ $offset ] ) ) {
            return $this->storage[ 'payload' ][ 'context' ][ $offset ];
        }

        return null;
    }

    public function offsetSet( $offset, $value ) {
        $this->storage[ 'payload' ][ 'context' ][ $offset ] = $value;
    }

    public function offsetUnset( $offset ) {
        unset( $this->storage[ 'payload' ][ 'context' ][ $offset ] );
    }

    public function __toString() {
        $data = $this->encrypt();
        return self::base64url_encode( json_encode( $data[ 'header' ] ) ) . "." . self::base64url_encode( json_encode( $data[ 'payload' ] ) ) . "." . $data[ 'signature' ];
    }

    private static function base64url_encode( $data ) {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    private static function base64url_decode( $data ) {
        return base64_decode( str_pad( strtr( $data, '-_', '+/' ), strlen( $data ) % 4, '=', STR_PAD_RIGHT ) );
    }

}