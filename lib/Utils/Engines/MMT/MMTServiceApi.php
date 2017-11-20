<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 10/10/17
 * Time: 12.55
 *
 */

/**
 * Created by PhpStorm.
 * User: davide
 * Date: 03/10/17
 * Time: 14:14
 */

namespace Engines\MMT;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'MMTServiceApiException.php';

class MMTServiceApi {

    const DEFAULT_SERVER_HOST = 'api.modernmt.eu';
    const DEFAULT_SERVER_PORT = 80;

    private $baseUrl;
    private $license;
    private $pluginVersion;
    private $platform;
    private $platformVersion;

    /**
     * MMTServiceApi constructor.
     * @param string $host
     * @param int $port
     * @param string $license
     * @param string $pluginVersion
     * @param string $platform
     * @param string $platformVersion
     */
    public function __construct($host = null, $port = null, $license = null,
                                $pluginVersion = null, $platform = null, $platformVersion = null) {
        $host = $host ? $host : self::DEFAULT_SERVER_HOST;
        $port = $port ? $port : self::DEFAULT_SERVER_PORT;
        $this->baseUrl = "http://$host:$port";
        $this->license = $license;
        $this->pluginVersion = $pluginVersion;
        $this->platform = $platform;
        $this->platformVersion = $platformVersion;
    }

    /**
     * @param string $license
     */
    public function setLicense($license) {
        $this->license = $license;
    }

    /* - Instance --------------------------------------------------------------------------------------------------- */

    public function getAvailableLanguages(){
        return $this->send('GET', "$this->baseUrl/languages");
    }

    /* - User ------------------------------------------------------------------------------------------------------- */

    public function signup($name, $email, $stripeToken) {
        return $this->send('POST', "$this->baseUrl/users", [
                'name' => $name, 'email' => $email, 'stripe_token' => $stripeToken
        ]);
    }

    public function me() {
        return $this->send('GET', "$this->baseUrl/users/me");
    }

    /* Memory ------------------------------------------------------------------------------------------------------- */

    public function getAllMemories() {
        return $this->send('GET', "$this->baseUrl/memories");
    }

    public function getMemoryById($id) {
        return $this->send('GET', "$this->baseUrl/memories/$id");
    }

    public function createMemory($name, $description = null, $externalId = null) {
        return $this->send('POST', "$this->baseUrl/memories", [
                'name' => $name, 'description' => $description, 'external_id' => $externalId
        ]);
    }

    public function deleteMemory($id) {
        return $this->send('DELETE', "$this->baseUrl/memories/$id");
    }

    public function updateMemory($id, $name, $description = null) {
        return $this->send('PUT', "$this->baseUrl/memories/$id", [
                'name' => $name, 'description' => $description
        ]);
    }

    public function connectMemories($externalIds) {
        return $this->send('POST', "$this->baseUrl/memories/connect", [
                'external_ids' => implode(',', $externalIds)
        ]);
    }

    public function addToMemoryContent($id, $source, $target, $sentence, $translation) {
        if (is_array($id)) {
            return $this->send('POST', "$this->baseUrl/memories/content", [
                    'memories' => empty($id) ? null : implode(',', $id),
                    'source' => $source, 'target' => $target, 'sentence' => $sentence, 'translation' => $translation
            ]);
        } else {
            return $this->send('POST', "$this->baseUrl/memories/$id/content", [
                    'source' => $source, 'target' => $target, 'sentence' => $sentence, 'translation' => $translation
            ]);
        }
    }

    public function updateMemoryContent($id, $source, $target, $sentence, $translation, $oldSentence, $oldTranslation) {
        if (is_array($id)) {
            return $this->send('PUT', "$this->baseUrl/memories/content", [
                    'memories' => empty($id) ? null : implode(',', $id),
                    'source' => $source, 'target' => $target, 'sentence' => $sentence, 'translation' => $translation,
                    'old_sentence' => $oldSentence, 'old_translation' => $oldTranslation
            ]);
        } else {
            return $this->send('PUT', "$this->baseUrl/memories/$id/content", [
                    'source' => $source, 'target' => $target, 'sentence' => $sentence, 'translation' => $translation,
                    'old_sentence' => $oldSentence, 'old_translation' => $oldTranslation
            ]);
        }
    }

    public function importIntoMemoryContent($id, $tmx, $compression = null) {
        return $this->send('POST', "$this->baseUrl/memories/$id/content", [
                'tmx' => $this->_setCulFileUpload( $tmx ) , 'compression' => $compression
        ], TRUE);
    }

    public function emptyMemoryContent($id) {
        return $this->send('DELETE', "$this->baseUrl/memories/$id/content");
    }

    public function getImportJob($uuid) {
        return $this->send('GET', "$this->baseUrl/import-jobs/$uuid");
    }

    /* Translation -------------------------------------------------------------------------------------------------- */

    public function getContextVectorFromText($source, $targets, $text, $hints = null, $limit = null) {
        return $this->send('GET', "$this->baseUrl/context-vector", [
                'source' => $source, 'targets' => implode(',', $targets), 'text' => $text,
                'hints' => ($hints ? implode(',', $hints) : null), 'limit' => $limit
        ]);
    }

    public function getContextVectorFromFile($source, $targets, $file, $compression = null, $hints = null, $limit = null) {
        return $this->send('GET', "$this->baseUrl/context-vector", [
                'source' => $source, 'targets' => implode(',', $targets), 'content' => $this->_setCulFileUpload( $file ),
                'compression' => $compression, 'hints' => ($hints ? implode(',', $hints) : null), 'limit' => $limit
        ], TRUE);
    }

    public function translate($source, $target, $text, $contextVector = null, $hints = null) {
        return $this->send('GET', "$this->baseUrl/translate", [
                'source' => $source, 'target' => $target, 'q' => $text, 'context_vector' => $contextVector,
                'hints' => ($hints ? implode(',', $hints) : null)
        ]);
    }

    /* - Low level utils -------------------------------------------------------------------------------------------- */

    protected function exec_curl($curl) {
        return curl_exec($curl);
    }

    protected function close_curl($curl) {
        curl_close($curl);
    }

    protected function _setCulFileUpload( $file ){
        if ( version_compare( PHP_VERSION, '5.5.0' ) >= 0 ) {
            return new \CURLFile( realpath( $file ) );
        } else {
            return '@' . realpath($file);
        }
    }

    /**
     * @param string $method
     * @param string $url
     * @param array $params
     * @param bool $multipart
     * @return mixed
     * @throws MMTServiceApiException
     */
    private function send($method, $url, $params = null, $multipart = FALSE) {
        if ($params) {
            $params = array_filter($params, function ($value) {
                return $value !== NULL;
            });
        }

        if (empty($params))
            $params = null;

        $headers = ["X-HTTP-Method-Override: $method"];

        if ($multipart) {
            array_push($headers, 'Content-Type: multipart/form-data; charset=utf-8');
        } else {
            array_push($headers, 'Content-Type: application/x-www-form-urlencoded; charset=utf-8');
            if ($params)
                $params = http_build_query($params);
        }

        if ($this->license)
            array_push($headers, "MMT-ApiKey: $this->license");

        if ($this->pluginVersion)
            array_push($headers, "MMT-PluginVersion: $this->pluginVersion");
        if ($this->platform)
            array_push($headers, "MMT-Platform: $this->platform");
        if ($this->platformVersion)
            array_push($headers, "MMT-PlatformVersion: $this->platformVersion");

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_HEADER, FALSE);

        if ( version_compare( PHP_VERSION, '5.5.0' ) >= 0 ) {
            /**
             * Added in PHP 5.5.0 with FALSE as the default value.
             * PHP 5.6.0 changes the default value to TRUE.
             */
            curl_setopt( $curl, CURLOPT_SAFE_UPLOAD, true );
        } else {
            /**
             * Needed to correctly handle "@file_path_to_upload"
             */
            curl_setopt( $curl, CURLOPT_SAFE_UPLOAD, false );
        }

        if (count($headers) > 0)
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        if ($params) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        }

        $result = $this->exec_curl($curl);
        $this->close_curl($curl);

        if ($result === FALSE)
            throw new MMTServiceApiException("ConnectionException", 500, "Unable to contact upstream server");

        return $this->parse($result);
    }

    private function parse($body) {
        $json = json_decode($body, TRUE);

        if (json_last_error() != JSON_ERROR_NONE)
            throw new MMTServiceApiException("ConnectionException", 500, "Unable to decode server response");

        if ($json["status"] != 200)
            throw MMTServiceApiException::fromJSONResponse($json);

        return isset($json['data']) ? $json['data'] : NULL;
    }

}