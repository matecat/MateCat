<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 16/02/17
 * Time: 20.12
 *
 */
class SimpleJWT implements ArrayAccess, JsonSerializable {

    protected $storage = [ 'header'    => null,
                           'payload'   => [ 'exp' => null, 'iss' => null, 'iat' => null, 'context' => null ],
                           'signature' => null
    ];

    protected static $secretKey;
    protected $timeToLive = 86400;
    protected $now;

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
        self::$secretKey = INIT::$AUTHSECRET;
        $this->now = time();

    }

    /**
     * @return array
     */
    public function sign() {
        $expire_date = $this->now + $this->timeToLive;
        $_storage = $this->storage;
        $_storage[ 'header' ] = [ "alg" => "HS256", "typ" => "JWT" ];
        $_storage[ 'payload' ][ 'exp' ] = $expire_date;
        $_storage[ 'payload' ][ 'iss' ] = INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER;
        $_storage[ 'payload' ][ 'iat' ] = $this->now;
        $_hash = hash_hmac( 'sha256', self::base64url_encode( json_encode( $_storage[ 'header' ] ) ) . "." . self::base64url_encode( json_encode( $_storage[ 'payload' ] ) ), self::$secretKey, true );
        $_storage[ 'signature' ] = self::base64url_encode( $_hash );
        return $_storage;
    }

    /**
     * @param $_storage
     *
     * @return bool
     * @throws DomainException
     */
    public static function isValid( $_storage ) {

        if( is_string( $_storage ) ) {
            $_storage = static::parseJWTString( $_storage );
        }

        $data_hash = $_storage[ 'signature' ];

        $expected_hash = hash_hmac( 'sha256', self::base64url_encode( json_encode( $_storage[ 'header' ] ) ) . "." . self::base64url_encode( json_encode( $_storage[ 'payload' ] ) ), self::$secretKey, true );
        //check if valid hash and expiration still in time

        if( $data_hash != self::base64url_encode( $expected_hash ) ){
            throw new DomainException( "Invalid Token Signature", 1 );
        }

        if( time() > $_storage[ 'payload' ][ 'exp' ] ){
            throw new DomainException( "Token Expired", 2 );
        }

        return true;

    }

    /**
     * @param $jwtString
     *
     * @return mixed
     * @throws DomainException
     */
    public static function getValidPayload( $jwtString ){

        if ( self::$secretKey == null ) {
            SimpleJWT::setSecretKey( INIT::$AUTHSECRET );
        }

        $jwtArray = self::parseJWTString( $jwtString );
        static::isValid( $jwtArray );
        return $jwtArray[ 'payload' ][ 'context' ];

    }

    public static function parseJWTString( $jwtString ){
        list( $header, $payload, $signature ) = explode( ".", $jwtString );
        $jwtArray = [
                'header' => json_decode( self::base64url_decode( $header ), true ),
                'payload' => json_decode( self::base64url_decode( $payload ) , true ),
                'signature' => $signature
        ];
        return $jwtArray;
    }

    /**
     * @param mixed $secretKey
     *
     */
    public static function setSecretKey( $secretKey ) {
        self::$secretKey = $secretKey;
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

    /**
     * @return int
     */
    public function getExpireDate() {
        return $this->now + $this->timeToLive;
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
        $data = $this->sign();
        return self::base64url_encode( json_encode( $data[ 'header' ] ) ) . "." . self::base64url_encode( json_encode( $data[ 'payload' ] ) ) . "." . $data[ 'signature' ];
    }

    public function jsonSerialize() {
        return $this->__toString();
    }

    public function encode(){
        return $this->__toString();
    }

    private static function base64url_encode( $data ) {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    private static function base64url_decode( $data ) {
        return base64_decode( str_pad( strtr( $data, '-_', '+/' ), strlen( $data ) % 4, '=', STR_PAD_RIGHT ) );
    }

}