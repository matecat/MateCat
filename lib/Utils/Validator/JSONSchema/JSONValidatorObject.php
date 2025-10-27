<?php

namespace Utils\Validator\JSONSchema;

use Exception;
use stdClass;
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
     * @var ?mixed The decoded JSON value or NULL when the JSON is null or an empty string.
     */
    protected mixed $decoded;

    /**
     * Whether getValid() has been called and $decoded is populated.
     * @var bool
     */
    protected bool $isDecoded = false;

    /**
     * JSONValidatorObject constructor.
     *
     * @param string $json The JSON string to be validated/decoded.
     */
    public function __construct( string $json ) {
        $this->json = $json;
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
    public function decode(): mixed {

        /**
         * If already validated, return the decoded value
         * Memoization pattern
         */
        if ( $this->isDecoded ) {
            return $this->decoded;
        }

        $this->decoded   = json_decode( $this->json == '' ? 'null' : $this->json, false, 512, JSON_THROW_ON_ERROR );
        $this->isDecoded = true;

        return $this->decoded;
    }

    /**
     * Alias for decode();
     *
     * @return mixed|null The decoded JSON value (array|object|scalar|null).
     * @throws Exception  If decoding fails or JSON is invalid.
     */
    public function getValue( bool $associative = false ): mixed {
        $val = $this->decode();

        if ( $val === null ) {
            return null;
        }

        if ( $associative ) {
            return $this->toArray( (object)$val );
        }

        return $val;

    }

    /**
     * Converts the given object into an associative array.
     *
     * This method recursively traverses the properties of the input object and converts them into an associative array.
     * If a property is itself an object or an array, the method calls itself recursively to handle nested structures.
     * Non-structured values (scalars) are directly added to the resulting array.
     *
     * @param object $object The object to be converted into an associative array.
     *                       Numeric indexed arrays are cast to objects to ensure
     *                       compatibility with the function signature.
     *
     * @return array An associative array representing the structure and data of the input object.
     */
    private function toArray( object $object ): array {
        $collector = [];
        foreach ( $object as $key => $value ) {

            // Determine if the value is structured (array or object).
            $isStructured = is_array( $value ) || is_object( $value );

            if ( $isStructured ) {
                // Recursively convert structured values into arrays.
                $collector[ $key ] = $this->toArray( (object)$value ); // Force cast to object to respect the function signature.
            } else {
                // Add scalar values directly to the resulting array.
                $collector[ $key ] = $value;
            }
        }

        return $collector;
    }

}