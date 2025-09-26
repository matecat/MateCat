<?php

namespace Utils\Validator\JSONSchema;

use Elastic\Transport\Exception\RuntimeException;
use Exception;
use Utils\Tools\Utils;
use Utils\Validator\Contracts\ValidatorObject;

/**
 * Class JSONValidatorObject
 *
 * Wraps a JSON string and provides lazy validation/decoding utilities for schema validators.
 * After calling getValid(), consumers can query whether the decoded payload is an array, object,
 * or a primitive value.
 *
 * Usage:
 * - Instantiate with a JSON string (and optionally associative=true to decode objects as arrays).
 * - Call getValid() to decode and validate JSON (throws on JSON errors).
 * - Call isArray(), isObject(), or isPrimitive() to inspect the decoded type.
 *
 * Notes:
 * - Decoding is memoized; further calls to getValid() return the cached result.
 * - Type-introspection methods require prior validation via getValid(), otherwise an exception is thrown.
 */
class JSONValidatorObject extends ValidatorObject {

    /**
     * Original JSON string to validate/decode.
     * @var string
     */
    protected string $json;

    /**
     * Decoded JSON value after validation (memoized).
     * Can be an array, object, scalar, or null depending on the JSON input.
     *
     * @var mixed|null
     */
    protected $decoded;

    /**
     * Whether getValid() has been called and $decoded is populated.
     * @var bool
     */
    protected bool $isDecoded = false;

    /**
     * True if the decoded value is an array.
     * @var bool
     */
    protected bool $isArray = false;

    /**
     * True if a decoded value is an object.
     * @var bool
     */
    protected bool $isObject = false;

    /**
     * True if the decoded value is neither array nor object (i.e., scalar or null).
     * @var bool
     */
    protected bool $isPrimitive = false;

    /**
     * If true, json_decode will return associative arrays instead of stdClass objects.
     * @var bool
     */
    protected bool $associative;

    /**
     * JSONValidatorObject constructor.
     *
     * @param string $json        The JSON string to be validated/decoded.
     * @param bool   $associative When true, objects are decoded as associative arrays.
     */
    public function __construct( string $json, bool $associative = false ) {
        $this->json        = $json;
        $this->associative = $associative;
    }

    /**
     * Decode and validate the JSON string, returning the decoded value.
     *
     * Behavior:
     * - Uses json_decode with the configured associative mode.
     * - Delegates error detection to Utils::raiseJsonExceptionError(), which throws on JSON errors.
     * - Memoizes the decoded result and sets type flags for later inspection.
     *
     * @return mixed|null The decoded JSON value (array|object|scalar|null).
     * @throws Exception  If decoding fails or JSON is invalid.
     */
    public function decode() {

        /**
         * If already validated, return the decoded value
         * Memoization pattern
         */
        if ( $this->isDecoded ) {
            return $this->decoded;
        }

        $this->decoded   = json_decode( $this->json == '' ? 'null' : $this->json, $this->associative );
        $this->isDecoded = true;
        Utils::raiseJsonExceptionError();
        if ( is_array( $this->decoded ) ) {
            $this->isArray = true;
        } elseif ( is_object( $this->decoded ) ) {
            $this->isObject = true;
        } else {
            $this->isPrimitive = true;
        }

        return $this->decoded;
    }

    /**
     * Alias for decode();
     *
     * @return mixed|null The decoded JSON value (array|object|scalar|null).
     * @throws Exception  If decoding fails or JSON is invalid.
     */
    public function getValue() {
        return $this->decode();
    }

    /**
     * Check if the validated JSON decodes to an array.
     *
     * @return bool True, when decoded value is an array.
     * @throws RuntimeException If called before getValid().
     */
    public function isArray(): bool {
        if ( !$this->isDecoded ) {
            throw new RuntimeException( 'Object not validated. Call JSONValidatorObject::getValid() first.' );
        }

        return $this->isArray;
    }

    /**
     * Check if the validated JSON decodes to an object.
     *
     * Note: When $associative is true, JSON objects decode to arrays, so this will be false.
     *
     * @return bool True, when decoded value is an object.
     * @throws RuntimeException If called before getValid().
     */
    public function isObject(): bool {
        if ( !$this->isDecoded ) {
            throw new RuntimeException( 'Object not validated. Call JSONValidatorObject::getValid() first.' );
        }

        return $this->isObject;
    }

    /**
     * Check if the validated JSON decodes to a primitive value (scalar or null).
     *
     * @return bool True, when decoded value is primitive (not array/object).
     * @throws RuntimeException If called before getValid().
     */
    public function isPrimitive(): bool {
        if ( !$this->isDecoded ) {
            throw new RuntimeException( 'Object not validated. Call JSONValidatorObject::getValid() first.' );
        }

        return $this->isPrimitive;
    }

}