<?php
/**
 * Created by PhpStorm.
 * This class defines constants and methods for managing LARA engine headers and their values.
 * It provides functionality to set and retrieve pre-shared keys, TUIDs, and translation origins.
 *
 * @author Domenico Lupinetti (hashashiyyin)
 * @contact domenico@translated.net / ostico@gmail.com
 * @date   26/08/25
 * @time   18:42
 */

namespace Utils\Engines\Lara;

use Iterator;

/**
 * Class Headers
 *
 * This class implements the Iterator interface to manage HTTP headers for the LARA engine.
 * It provides methods to set and retrieve specific headers, such as the TUID and translation origin.
 */
class Headers implements Iterator
{

    /**
     * Header name for the LARA pre-shared key.
     * This constant is used to identify the HTTP header for the pre-shared key.
     *
     * @var string
     */
    const string LARA_PRE_SHARED_KEY_HEADER = 'X-Lara-Engine-Pre-Shared-Key'; //TODO: to be removed when Lara will read directly from the internal queue

    /**
     * Header name for the LARA TUID.
     * This constant is used to identify the HTTP header for the TUID (Translation Unique Identifier).
     *
     * @var string
     */
    const string LARA_TUID_HEADER = 'X-Lara-Engine-Tuid';

    /**
     * Header name for the LARA translation origin.
     * This constant is used to identify the HTTP header for the translation origin.
     *
     * @var string
     */
    const string LARA_TRANSLATION_ORIGIN_HEADER = 'X-Lara-Engine-Translation-Origin';

    /**
     * Stores the headers as an associative array.
     *
     * @var HeaderField[]
     */
    private array $headers = [];

    /**
     * Stores the keys of the headers for iteration.
     *
     * @var array
     */
    private array $keys;

    /**
     * Tracks the current position for iteration.
     *
     * @var int
     */
    private int $position;

    /**
     * Constructor for the Headers class.
     * Initializes the pre-shared key, TUID, and translation origin.
     *
     * @param string|null $tuid The TUID value.
     * @param string|null $translationOrigin The translation origin value (optional).
     */
    public function __construct(?string $tuid = null, ?string $translationOrigin = null)
    {
        if ($tuid) {
            $this->headers[self::LARA_TUID_HEADER] = new HeaderField(self::LARA_TUID_HEADER, $tuid);
        }

        if ($translationOrigin) {
            $this->headers[self::LARA_TRANSLATION_ORIGIN_HEADER] = new HeaderField(self::LARA_TRANSLATION_ORIGIN_HEADER, $translationOrigin);
        }

        $this->keys = array_keys($this->headers);
        $this->position = 0;
    }

    /**
     * Retrieves the TUID as an object containing the header name and its value.
     *
     * @return ?HeaderField An object with 'key' and 'value' properties.
     */
    public function getTuid(): ?HeaderField
    {
        if (isset($this->headers[self::LARA_TUID_HEADER])) {
            return clone $this->headers[self::LARA_TUID_HEADER];
        }

        return null;
    }

    /**
     * Retrieves the translation origin as an object containing the header name and its value.
     *
     * @return ?HeaderField An object with 'key' and 'value' properties.
     */
    public function getTranslationOrigin(): ?HeaderField
    {
        if (isset($this->headers[self::LARA_TRANSLATION_ORIGIN_HEADER])) {
            return clone $this->headers[self::LARA_TRANSLATION_ORIGIN_HEADER];
        }

        return null;
    }

    /**
     * Sets the value of the TUID.
     *
     * @param string $tuid The TUID to set.
     *
     * @return $this Returns the current instance for method chaining.
     */
    public function setTuid(string $tuid): Headers
    {
        $this->headers[self::LARA_TUID_HEADER] = new HeaderField(self::LARA_TUID_HEADER, $tuid);
        $this->keys = array_keys($this->headers);

        return $this;
    }

    /**
     * Sets the value of the translation origin.
     *
     * @param string $translationOrigin The translation origin to set.
     *
     * @return $this Returns the current instance for method chaining.
     */
    public function setTranslationOrigin(string $translationOrigin): Headers
    {
        $this->headers[self::LARA_TRANSLATION_ORIGIN_HEADER] = new HeaderField(self::LARA_TRANSLATION_ORIGIN_HEADER, $translationOrigin);
        $this->keys = array_keys($this->headers);

        return $this;
    }

    /**
     * Returns the current header field in the iteration.
     *
     * @return HeaderField The current header field.
     */
    public function current(): HeaderField
    {
        return $this->headers[$this->keys[$this->position]];
    }

    /**
     * Returns the key of the current header field in the iteration.
     *
     * @return string The key of the current header field.
     */
    public function key(): string
    {
        return $this->keys[$this->position];
    }

    /**
     * Moves the iterator to the next header field.
     *
     * @return void
     */
    public function next(): void
    {
        ++$this->position;
    }

    /**
     * Rewinds the iterator to the first header field.
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Checks if the current position is valid in the iteration.
     *
     * @return bool True if the current position is valid, false otherwise.
     */
    public function valid(): bool
    {
        return isset($this->keys[$this->position]);
    }

    /**
     * Returns an associative array of header keys and their non-empty values.
     *
     * Iterates over the internal `$headers` array and includes only those headers
     * whose `getValue()` method returns a non-empty value. The result is an array
     * where each key is the header name and each value is the corresponding header value.
     *
     * @return array An associative array of header keys and their non-empty values.
     */
    public function getArrayCopy(): array
    {
        $headers = [];

        foreach ($this->headers as $header) {
            if (!empty($header->getValue())) {
                $headers[$header->getKey()] = $header->getValue();
            }
        }

        return $headers;
    }

}