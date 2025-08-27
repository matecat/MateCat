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

class Headers {

    /**
     * Header name for the LARA pre-shared key.
     * This constant is used to identify the HTTP header for the pre-shared key.
     *
     * @var string
     */
    const LARA_PRE_SHARED_KEY_HEADER = 'X-Lara-Engine-Pre-Shared-Key'; //TODO: to be removed when Lara will read directly from the internal queue

    /**
     * Header name for the LARA TUID.
     * This constant is used to identify the HTTP header for the TUID (Translation Unique Identifier).
     *
     * @var string
     */
    const LARA_TUID_HEADER = 'X-Lara-Engine-Tuid';

    /**
     * Header name for the LARA translation origin.
     * This constant is used to identify the HTTP header for the translation origin.
     *
     * @var string
     */
    const LARA_TRANSLATION_ORIGIN_HEADER = 'X-Lara-Engine-Translation-Origin';

    /**
     * Stores the value of the pre-shared key.
     *
     * @var string
     */
    private string $preSharedKey;

    /**
     * Stores the value of the TUID.
     *
     * @var string
     */
    private string $tuid;

    /**
     * Stores the value of the translation origin.
     *
     * @var string
     */
    private string $translationOrigin;

    /**
     * Constructor for the Headers class.
     * Initializes the pre-shared key, TUID, and translation origin.
     *
     * @param string $preSharedKey      The pre-shared key value.
     * @param string $tuid              The TUID value.
     * @param string $translationOrigin The translation origin value (optional).
     */
    public function __construct( string $preSharedKey, string $tuid, string $translationOrigin = '' ) {
        $this->preSharedKey      = $preSharedKey;
        $this->tuid              = $tuid;
        $this->translationOrigin = $translationOrigin;
    }

    /**
     * Retrieves the pre-shared key as an object containing the header name and its value.
     *
     * @return HeaderField An object with 'key' and 'value' properties.
     */
    public function getPreSharedKey(): HeaderField {
        return new HeaderField( self::LARA_PRE_SHARED_KEY_HEADER, $this->preSharedKey );
    }

    /**
     * Retrieves the TUID as an object containing the header name and its value.
     *
     * @return HeaderField An object with 'key' and 'value' properties.
     */
    public function getTuid(): HeaderField {
        return new HeaderField( self::LARA_TUID_HEADER, $this->tuid );
    }

    /**
     * Retrieves the translation origin as an object containing the header name and its value.
     *
     * @return HeaderField An object with 'key' and 'value' properties.
     */
    public function getTranslationOrigin(): HeaderField {
        return new HeaderField( self::LARA_TRANSLATION_ORIGIN_HEADER, $this->translationOrigin );
    }

    /**
     * Retrieves all headers as an associative array.
     * Combines the pre-shared key, TUID, and translation origin headers.
     *
     * @return array An associative array of header fields.
     */
    public function getArrayCopy(): array {
        return array_merge(
                $this->getPreSharedKey()->getArrayCopy(),
                $this->getTuid()->getArrayCopy(),
                ( $this->getTranslationOrigin()->getValue() !== '' ) ? $this->getTranslationOrigin()->getArrayCopy() : []
        );
    }

}