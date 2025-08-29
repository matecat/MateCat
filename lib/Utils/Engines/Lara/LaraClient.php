<?php
/**
 * Created by PhpStorm.
 * This class extends the Translator class and provides an implementation
 * for interacting with the LARA translation engine. It initializes the
 * HTTP client and manages memories, documents, and glossaries.
 *
 * @author Domenico Lupinetti
 * @contact domenico@translated.net / ostico@gmail.com
 * @date   27/08/25
 * @time   16:09
 */

namespace Utils\Engines\Lara;

use Lara\LaraCredentials;
use Lara\Translator;
use Lara\TranslatorOptions;
use Utils\Registry\AppConfig;

class LaraClient extends Translator {

    /**
     * Constructor for the LaraClient class.
     * Initializes the HTTP client and sets up memories, documents, and glossaries.
     *
     * @param LaraCredentials        $credentials The credentials required for authentication.
     * @param TranslatorOptions|null $options     Optional translator options, including the server URL.
     *
     */
    public function __construct( LaraCredentials $credentials, TranslatorOptions $options = null ) {
        parent::__construct( $credentials, $options );
        // Sets an extra header for the HTTP client using the pre-shared key.
        $this->client->setExtraHeader( Headers::LARA_PRE_SHARED_KEY_HEADER, AppConfig::$LARA_PRE_SHARED_KEY_HEADER );
    }

}