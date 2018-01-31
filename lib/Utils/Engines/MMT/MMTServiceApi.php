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
    const DEFAULT_SERVER_PORT = 443;

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
        $this->baseUrl = "https://$host:$port";
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

    /**
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function getAvailableLanguages(){
        return $this->send('GET', "$this->baseUrl/languages");
    }

    /* - User ------------------------------------------------------------------------------------------------------- */

    /**
     * @param $name
     * @param $email
     * @param $stripeToken
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function signup($name, $email, $stripeToken) {
        return $this->send('POST', "$this->baseUrl/users", [
                'name' => $name, 'email' => $email, 'stripe_token' => $stripeToken
        ]);
    }

    /**
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function me() {
        return $this->send('GET', "$this->baseUrl/users/me");
    }

    /* Memory ------------------------------------------------------------------------------------------------------- */

    /**
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function getAllMemories() {
        return $this->send('GET', "$this->baseUrl/memories");
    }

    /**
     * @param $id
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function getMemoryById($id) {
        return $this->send('GET', "$this->baseUrl/memories/$id");
    }

    /**
     * @param      $name
     * @param string|null $description
     * @param null $externalId
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function createMemory($name, $description = null, $externalId = null) {
        return $this->send('POST', "$this->baseUrl/memories", [
                'name' => $name, 'description' => $description, 'external_id' => $externalId
        ]);
    }

    /**
     * @param $id
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function deleteMemory($id) {
        return $this->send('DELETE', "$this->baseUrl/memories/$id");
    }

    /**
     * @param      $id
     * @param      $name
     * @param string|null $description
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function updateMemory($id, $name, $description = null) {
        return $this->send('PUT', "$this->baseUrl/memories/$id", [
                'name' => $name, 'description' => $description
        ]);
    }

    /**
     * @param $externalIds
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function connectMemories($externalIds) {
        return $this->send('POST', "$this->baseUrl/memories/connect", [
                'external_ids' => implode(',', $externalIds)
        ]);
    }

    /**
     * @param $id
     * @param $source
     * @param $target
     * @param $sentence
     * @param $translation
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
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

    /**
     * @param $id
     * @param $source
     * @param $target
     * @param $sentence
     * @param $translation
     * @param $oldSentence
     * @param $oldTranslation
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
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

    /**
     * @param      $id
     * @param      $tmx
     * @param string|null $compression
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function importIntoMemoryContent($id, $tmx, $compression = null) {
        return $this->send('POST', "$this->baseUrl/memories/$id/content", [
                'tmx' => $this->_setCulFileUpload( $tmx ) , 'compression' => $compression
        ], TRUE);
    }

    /**
     * @param $id
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function emptyMemoryContent($id) {
        return $this->send('DELETE', "$this->baseUrl/memories/$id/content");
    }

    /**
     * @param $uuid
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function getImportJob($uuid) {
        return $this->send('GET', "$this->baseUrl/import-jobs/$uuid");
    }

    /* Translation -------------------------------------------------------------------------------------------------- */

    /**
     * @param      $source
     * @param      $targets
     * @param      $text
     * @param array|null $hints
     * @param mixed $limit
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function getContextVectorFromText($source, $targets, $text, $hints = null, $limit = null) {
        return $this->send('GET', "$this->baseUrl/context-vector", [
                'source' => $source, 'targets' => implode(',', $targets), 'text' => $text,
                'hints' => ($hints ? implode(',', $hints) : null), 'limit' => $limit
        ]);
    }

    /**
     * @param      $source
     * @param      $targets
     * @param      $file
     * @param string|null $compression
     * @param array|null $hints
     * @param mixed $limit
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function getContextVectorFromFile($source, $targets, $file, $compression = null, $hints = null, $limit = null) {
        return $this->send('GET', "$this->baseUrl/context-vector", [
                'source' => $source, 'targets' => implode(',', $targets), 'content' => $this->_setCulFileUpload( $file ),
                'compression' => $compression, 'hints' => ($hints ? implode(',', $hints) : null), 'limit' => $limit
        ], TRUE);
    }

    /**
     * @param      $source
     * @param      $target
     * @param      $text
     * @param string|null $contextVector
     * @param array|null $hints
     * @param int|null $projectId
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function translate($source, $target, $text, $contextVector = null, $hints = null, $projectId = null ) {
        return $this->send('GET', "$this->baseUrl/translate", [
                'source' => $source, 'target' => $target, 'q' => $text, 'context_vector' => $contextVector,
                'hints' => ($hints ? implode(',', $hints) : null), 'project_id' => $projectId
        ]);
    }

    /* - Low level utils -------------------------------------------------------------------------------------------- */

    /**
     * @param $curl
     *
     * @return mixed
     */
    protected function exec_curl($curl) {
        return curl_exec($curl);
    }

    /**
     * @param $curl
     */
    protected function close_curl($curl) {
        curl_close($curl);
    }

    /**
     * @param $file
     *
     * @return \CURLFile|string
     */
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
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, TRUE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);

        if( version_compare( PHP_VERSION, '5.5.0' ) >= 0 ){
            /**
             * Added in PHP 5.5.0 with FALSE as the default value.
             * PHP 5.6.0 changes the default value to TRUE.
             * For php >= 5.5.0 we use \CURLFile , so we can force and set safe upload to true
             *
             * @see MMTServiceApi::_setCulFileUpload()
             */
            curl_setopt($curl, CURLOPT_SAFE_UPLOAD, true);
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

    /**
     * @param string $body
     *
     * @return string|null
     * @throws MMTServiceApiException
     */
    private function parse($body) {
        $json = json_decode($body, TRUE);

        if (json_last_error() != JSON_ERROR_NONE)
            throw new MMTServiceApiException("ConnectionException", 500, "Unable to decode server response");

        if ($json["status"] != 200)
            throw MMTServiceApiException::fromJSONResponse($json);

        return isset($json['data']) ? $json['data'] : NULL;
    }

}