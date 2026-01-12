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

use Lara\AccessKey;
use Lara\AuthToken;
use Lara\Documents;
use Lara\Glossaries;
use Lara\Internal\HttpClient;
use Lara\LaraException;
use Lara\Memories;
use Lara\Translator;
use Utils\Registry\AppConfig;

class LaraClient extends Translator
{

    /**
     * Constructor for the LaraClient class.
     * Initializes the HTTP client and sets up memories, documents, and glossaries.
     *
     * @param AccessKey $credentials The credentials required for authentication.
     *
     * @throws LaraException
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct(AccessKey $credentials)
    {
        $this->client = new class('https://api.laratranslate.com', $credentials) extends HttpClient implements HttpClientInterface {

            public function __construct(?string $baseUrl = null, AccessKey|AuthToken|null $credentials = null)
            {
                parent::__construct($baseUrl, $credentials);
            }

            public function authenticate(): string
            {
                return parent::authenticate();
            }

        };

        $this->memories = new Memories($this->client);
        $this->documents = new Documents($this->client);
        $this->glossaries = new Glossaries($this->client);

        // Sets an extra header for the HTTP client using the pre-shared key.
        $this->client->setExtraHeader(Headers::LARA_PRE_SHARED_KEY_HEADER, AppConfig::$LARA_PRE_SHARED_KEY_HEADER);
    }

    /**
     * Retrieves the HTTP client instance.
     *
     * @return HttpClientInterface & HttpClient
     */
    public function getHttpClient(): HttpClientInterface & HttpClient
    {
        /** @var HttpClientInterface & HttpClient */
        return $this->client;
    }

}