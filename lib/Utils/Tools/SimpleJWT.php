<?php

namespace Utils\Tools;

use ArrayAccess;
use DomainException;
use JsonSerializable;
use Stringable;
use UnexpectedValueException;
use Utils\Registry\AppConfig;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 16/02/17
 * Time: 20.12
 *
 */
class SimpleJWT implements ArrayAccess, JsonSerializable, Stringable
{

    protected array $storage = [
            'header'    => null,
            'payload'   => ['exp' => null, 'iss' => null, 'iat' => null, 'context' => null],
            'signature' => null
    ];

    protected static ?string $secretKey  = null;
    protected int            $timeToLive = 86400;
    protected int            $now;

    /**
     * SimpleJWT constructor.
     *
     * @param array $hashMap
     *
     * @throws UnexpectedValueException
     */
    public function __construct(array $hashMap = [])
    {
        if ([] !== $hashMap && array_keys($hashMap) === range(0, count($hashMap) - 1)) {
            throw new UnexpectedValueException('Provided Array is not associative.');
        }

        $this->storage[ 'payload' ][ 'context' ] = $hashMap;
        self::$secretKey                         = AppConfig::$AUTHSECRET;
        $this->now                               = time();
    }

    /**
     * @return array
     */
    public function sign(): array
    {
        $expire_date                    = $this->now + $this->timeToLive;
        $_storage                       = $this->storage;
        $_storage[ 'header' ]           = ["alg" => "HS256", "typ" => "JWT"];
        $_storage[ 'payload' ][ 'exp' ] = $expire_date;
        $_storage[ 'payload' ][ 'iss' ] = AppConfig::MATECAT_USER_AGENT . AppConfig::$BUILD_NUMBER;
        $_storage[ 'payload' ][ 'iat' ] = $this->now;
        $_hash                          = hash_hmac('sha256', self::base64url_encode(json_encode($_storage[ 'header' ])) . "." . self::base64url_encode(json_encode($_storage[ 'payload' ])), self::$secretKey, true);
        $_storage[ 'signature' ]        = self::base64url_encode($_hash);

        return $_storage;
    }

    /**
     * @param array|string $_storage
     *
     * @return bool
     */
    public static function isValid(array|string $_storage): bool
    {
        if (is_string($_storage)) {
            $_storage = static::parseJWTString($_storage);
        }

        $data_hash = $_storage[ 'signature' ];

        $expected_hash = hash_hmac('sha256', self::base64url_encode(json_encode($_storage[ 'header' ])) . "." . self::base64url_encode(json_encode($_storage[ 'payload' ])), self::$secretKey, true);
        //check if valid hash and expiration still in time

        if ($data_hash != self::base64url_encode($expected_hash)) {
            throw new DomainException("Invalid Token Signature", 1);
        }

        if (time() > $_storage[ 'payload' ][ 'exp' ]) {
            throw new UnexpectedValueException("Token Expired", 2);
        }

        return true;
    }

    /**
     * @param string $jwtString
     *
     * @return mixed
     * @throws DomainException
     */
    public static function getValidPayload(string $jwtString): mixed
    {
        if (self::$secretKey == null) {
            SimpleJWT::setSecretKey(AppConfig::$AUTHSECRET);
        }

        $jwtArray = self::parseJWTString($jwtString);
        static::isValid($jwtArray);

        return $jwtArray[ 'payload' ][ 'context' ];
    }

    /**
     * @param string $jwtString
     *
     * @return array
     */
    public static function parseJWTString(string $jwtString): array
    {
        [$header, $payload, $signature] = explode(".", $jwtString);

        return [
                'header'    => json_decode(self::base64url_decode($header), true),
                'payload'   => json_decode(self::base64url_decode($payload), true),
                'signature' => $signature
        ];
    }

    /**
     * @param string $secretKey
     *
     */
    public static function setSecretKey(string $secretKey): void
    {
        self::$secretKey = $secretKey;
    }

    /**
     * @param int $timeToLive
     *
     * @return $this
     * @throws UnexpectedValueException
     */
    public function setTimeToLive(int $timeToLive): SimpleJWT
    {
        if (!is_numeric($timeToLive) || $timeToLive < 0) {
            throw new UnexpectedValueException('Time To Live must be a positive integer');
        }

        $this->timeToLive = $timeToLive;

        return $this;
    }

    /**
     * @return int
     */
    public function getExpireDate(): int
    {
        return $this->now + $this->timeToLive;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->storage[ 'payload' ][ 'context' ][ $offset ]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (isset($this->storage[ 'payload' ][ 'context' ][ $offset ])) {
            return $this->storage[ 'payload' ][ 'context' ][ $offset ];
        }

        return null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->storage[ 'payload' ][ 'context' ][ $offset ] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->storage[ 'payload' ][ 'context' ][ $offset ]);
    }

    public function __toString(): string
    {
        $data = $this->sign();

        return self::base64url_encode(json_encode($data[ 'header' ])) . "." . self::base64url_encode(json_encode($data[ 'payload' ])) . "." . $data[ 'signature' ];
    }

    public function jsonSerialize(): string
    {
        return $this->__toString();
    }

    public function encode(): string
    {
        return $this->__toString();
    }

    private static function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @param $data
     *
     * @return false|string
     */
    private static function base64url_decode($data): false|string
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '='));
    }

}