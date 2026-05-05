<?php

namespace Utils\Tools;

use ArrayAccess;
use DomainException;
use JsonSerializable;
use Stringable;
use UnexpectedValueException;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 16/02/17
 * Time: 20.12
 *
 * @property string iss
 * @property string sub
 * @property string aud
 * @property int exp
 * @property int nbf
 * @property int iat
 * @property string jti
 * @property mixed simple.jwt.claims
 */
class SimpleJWT implements ArrayAccess, JsonSerializable, Stringable
{

    private array $privateClaims = ['iss', 'sub', 'aud', 'exp', 'nbf', 'iat', 'jti'];

    private array $storage =
        [
            // Set the JWT header: HMAC-SHA256 algorithm and JWT type.
            'header' => ["alg" => "HS256", "typ" => "JWT"],
            'payload' => [
                'iss' => null,
                'sub' => null,
                'aud' => null,
                'exp' => null,
                'nbf' => null,
                'iat' => null,
                'jti' => null,
                'simple.jwt.claims' => null
            ],
            'signature' => null
        ];

    private ?string $secretKey;
    private int $timeToLive;
    private int $now;
    private string $customClaimsNamespace;

    /**
     * SimpleJWT constructor.
     *
     * @param array $hashMap
     * @param string $issuer
     * @param string $authSecret
     * @param int $ttl
     */
    public function __construct(
        array $hashMap = [],
        string $issuer = 'simple.jwt.claims',
        string $authSecret = '',
        int $ttl = 86400
    ) {
        // set the namespace for custom claims
        $this->customClaimsNamespace = $issuer;

        foreach ($hashMap as $key => $value) {
            $this[$key] = $value; //use the magic method to filter the payload for the private claims
        }

        $this->secretKey = $authSecret;
        $this->now = time();
        $this->timeToLive = $ttl;
    }

    /**
     * Generates a signed JWT (JSON Web Token) based on the current storage values.
     *
     * @return array The assembled JWT components, including the header, payload, and signature.
     */
    public function sign(): array
    {
        // Calculate the token expiration timestamp (current time + TTL).
        $expire_date = $this->now + $this->timeToLive;

        // create a copy of the storage array
        $_storage = $this->storage;

        // Filter out null/empty entries from the storage payload (only keep non-empty sections).
        $_storage['payload'] = array_filter(
            $this->storage['payload'],
            fn($value) => !is_null($value) && $value !== ''
        );

        // Set standard JWT claims in the payload.
        $_storage['payload']['exp'] = $expire_date; // Expiration time

        // A common and practical approach is to use a namespaced name for your claim,
        // typically using a URI prefix you control (e.g., https://example.com/user_role),
        // ensuring uniqueness without needing formal IANA registration.
        $_storage['payload']['iss'] = $_storage['payload']['iss'] ?? $this->customClaimsNamespace;

        $_storage['payload']['iat'] = $this->now; // Issued-at time

        if (
            ($_storage['payload']['nbf'] ?? null) !== null &&
            (
                !is_numeric($_storage['payload']['nbf']) ||
                (int)$_storage['payload']['nbf'] < 0 ||
                (int)$_storage['payload']['nbf'] > $expire_date
            )
        ) {
            throw new UnexpectedValueException("Invalid nbf value: " . $_storage['payload']['nbf']);
        }

        // Compute the HMAC-SHA256 signature over base64url-encoded header.payload.
        $_hash = hash_hmac(
            'sha256',
            self::base64url_encode(json_encode($_storage['header'])) .
            "." .
            self::base64url_encode(json_encode($_storage['payload'])),
            $this->secretKey,
            true
        );

        // store the raw HMAC output as the token signature.
        $_storage['signature'] = $_hash;

        // Return the fully assembled JWT components (header, payload, signature).
        return $_storage;
    }

    /**
     * Creates a SimpleJWT instance from a raw JWT string.
     *
     * @param string $jwtString The JWT string to be parsed and validated.
     * @return SimpleJWT       A SimpleJWT object initialized with the JWT data.
     */
    private static function getInstanceFromString(string $jwtString, string $secretKey = ''): SimpleJWT
    {
        // Parse the JWT string into its internal representation (header, payload, signature)
        $storage = self::parseJWTString($jwtString);

        if (!empty($secretKey)) {
            // Validate the parsed JWT data (e.g., structure, claims, signature)
            self::isValid($storage, $secretKey);
        }

        // Create a new SimpleJWT object
        $that = new SimpleJWT();

        // Store the parsed JWT data inside the new object
        $that->storage = $storage;

        // Set the current time as the issued-at time (iat)
        $that->now = $that->storage['payload']['iat'] ?? time();

        // Set the token expiration time (exp)
        $that->timeToLive = $that->storage['payload']['exp'] ?? null;

        // Set the namespace for custom claims
        $that->customClaimsNamespace = $that->storage['payload']['iss'] ?? 'simple.jwt.claims';

        // reset the signature
        $that->storage['signature'] = null;

        // Return the initialized and validated SimpleJWT instance
        return $that;
    }

    /**
     * @param string $jwtString The JSON Web Token as a string to be validated and parsed into an instance.
     * @param string $secretKey Optional secret key for validating the token.
     * @return SimpleJWT Validated instance of the SimpleJWT class created from the given token string.
     */
    public static function getValidatedInstanceFromString(string $jwtString, string $secretKey): SimpleJWT
    {
        return self::getInstanceFromString($jwtString, $secretKey);
    }

    /**
     * @param string $jwtString The JWT string to create an instance from.
     * @return SimpleJWT An instance of SimpleJWT created from the given JWT string without validation.
     */
    public static function getNotValidatedInstanceFromString(string $jwtString): SimpleJWT
    {
        return self::getInstanceFromString($jwtString);
    }

    /**
     * Validates the provided JWT token either as a compact string or as a parsed array.
     *
     * @param array|string $_storage The JWT token to be validated, either as an array
     * containing `header`, `payload`, and `signature` fields, or as a compact JWT string.
     *
     * @return bool Returns true if the token is valid; otherwise, exceptions are thrown for invalid tokens.
     *
     * @throws DomainException If the token signature does not match the expected signature.
     * @throws UnexpectedValueException If the token has expired or if it is not yet valid (based on `exp` and `nbf` claims).
     */
    public static function isValid(array|string $_storage, string $secretKey = ''): bool
    {
        // If a compact JWT string is passed, decode it into header/payload/signature array.
        if (is_string($_storage)) {
            $_storage = static::parseJWTString($_storage);
        }

        // Signature taken from the provided token.
        $data_hash = $_storage['signature'];

        // Recompute the expected HMAC-SHA256 signature from header and payload using the shared secret key.
        $expected_hash = hash_hmac(
            'sha256',
            self::base64url_encode(json_encode($_storage['header'])) .
            "." .
            self::base64url_encode(json_encode($_storage['payload'])),
            $secretKey,
            true // return raw binary output
        );

        // Verify that the provided signature matches the recomputed one.
        if ($data_hash != $expected_hash) {
            throw new DomainException("Invalid Token Signature", 1);
        }

        // Check token expiration: if the current time is greater than `exp`, the token is expired.
        // If `exp` is missing, treat it as non-expiring (PHP_INT_MAX).
        if (time() > ($_storage['payload']['exp'] ?? PHP_INT_MAX)) {
            throw new UnexpectedValueException("Token Expired", 2);
        }

        // Check "not before" (`nbf`): if set and the current time is before it, the token is not yet valid.
        if (($_storage['payload']['nbf'] ?? null) && time() < $_storage['payload']['nbf']) {
            throw new UnexpectedValueException("Token not valid yet", 3);
        }

        // All checks passed: the token is valid.
        return true;
    }

    /**
     * @param string $jwtString
     *
     * @return array
     */
    private static function parseJWTString(string $jwtString): array
    {
        [$header, $payload, $signature] = explode(".", $jwtString);

        return [
            'header' => json_decode(self::base64url_decode($header), true),
            'payload' => json_decode(self::base64url_decode($payload), true),
            'signature' => self::base64url_decode($signature)
        ];
    }

    /**
     * @param string $secretKey
     * @return SimpleJWT
     */
    public function setSecretKey(string $secretKey): SimpleJWT
    {
        $this->secretKey = $secretKey;

        return $this;
    }

    /**
     * @param int $timeToLive
     *
     * @return $this
     * @throws UnexpectedValueException
     */
    public function setTimeToLive(int $timeToLive): SimpleJWT
    {
        if ($timeToLive < 0) {
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
        if (in_array($offset, $this->privateClaims)) {
            return isset($this->storage['payload'][$offset]);
        }
        return isset($this->storage['payload'][$this->customClaimsNamespace][$offset]);
    }

    /**
     * Returns the payload of the JWT.
     * @return array
     */
    public function getPayload(): array
    {
        return $this->storage['payload'][$this->customClaimsNamespace ?? 'simple.jwt.claims'] ?? [];
    }

    /**
     * Retrieve the value associated with the specified offset.
     *
     * @param mixed $offset The offset for which the value is to be retrieved. It can be of any type.
     *
     * @return mixed The value associated with the given offset. Returns null if the offset does not exist.
     */
    public function offsetGet(mixed $offset): mixed
    {
        // If the key is one of the standard/private JWT claims, return it directly from the top-level payload
        if (in_array($offset, $this->privateClaims, true)) {
            return $this->storage['payload'][$offset] ?? null;
        }

        // Otherwise, treat the key as a field inside the "simple.jwt.claims" array and return that (or null if missing)
        return $this->storage['payload'][$this->customClaimsNamespace][$offset] ?? null;
    }

    /**
     * Sets a value at the specified offset within the JWT payload. Depending on the offset,
     * the value is either assigned as a reserved claim or under the custom namespace for
     * application-specific data.
     *
     * @param mixed $offset The key or offset where the value should be stored.
     * @param mixed $value The value to set at the specified offset.
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        // If this is a standard/private JWT claim (iss, sub, aud, exp, nbf, iat, jti)...
        if (in_array($offset, $this->privateClaims, true)) {
            // ...store it directly as a top‑level payload field.
            $this->storage['payload'][$offset] = $value;

            // Special handling when changing the issuer (`iss`) claim:
            if ($offset == 'iss') {
                // Move all existing custom claims from the old namespace...
                $tmp = $this->storage['payload'][$this->customClaimsNamespace];
                unset($this->storage['payload'][$this->customClaimsNamespace]);

                // ...update the namespace to the new issuer value...
                $this->customClaimsNamespace = $value;

                // ...and reattach custom claims under the new namespace key.
                $this->storage['payload'][$this->customClaimsNamespace] = $tmp;
            }
        } else {
            // For non-standard claims, store them as namespaced custom claims.
            $this->storage['payload'][$this->customClaimsNamespace][$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        if (in_array($offset, $this->privateClaims)) {
            unset($this->storage['payload'][$offset]);
        }
        unset($this->storage['payload'][$this->customClaimsNamespace][$offset]);
    }

    public function __toString(): string
    {
        $data = $this->sign();

        return self::base64url_encode(json_encode($data['header'])) .
            "." .
            self::base64url_encode(json_encode($data['payload'])) .
            "." .
            self::base64url_encode($data['signature']);
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