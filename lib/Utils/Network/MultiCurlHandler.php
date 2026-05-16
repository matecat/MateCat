<?php
/**
 * Created by PhpStorm.
 */

namespace Utils\Network;

use CurlHandle;
use CurlMultiHandle;
use RuntimeException;
use Utils\Logger\LoggerFactory;
use Utils\Logger\MatecatLogger;

/**
 * Manager for a Multi Curl connection
 *
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/04/14
 * Time: 18.35
 *
 */
class MultiCurlHandler
{

    /**
     * The multi curl resource
     *
     * @var ?CurlMultiHandle
     */
    protected ?CurlMultiHandle $multi_handler = null;

    /**
     * Pool to manage the curl instances and retrieve them by unique hash identifier
     *
     * @var CurlHandle[]
     */
    protected array $curl_handlers = [];

    /**
     * Array to manage the requests for headers
     *
     * @var array<string, true|string[]>
     */
    protected array $curl_headers_requests = [];

    /**
     * Array to store the options passed when creating the resource, for debug purpose
     *
     * @var array<string, array<int, mixed>|null>
     */
    protected array $curl_options_requests = [];

    /**
     * Container for the curl results
     *
     * @var array<string, string|false|null>
     */
    protected array $multi_curl_results = [];

    /**
     * Container for the curl info results
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $multi_curl_info = [];

    /**
     * Container for the curl logs
     * @var array<string, array<string, mixed>>
     */
    protected array $multi_curl_log = [];

    public bool $verbose = false;
    public bool $high_verbosity = false;
    private ?MatecatLogger $logger;

    /**
     * Class Constructor, init the multi curl handler
     *
     */
    public function __construct(?MatecatLogger $logger = null)
    {
        $this->multi_handler = curl_multi_init();
        // Default is 10
        curl_multi_setopt($this->multi_handler, CURLMOPT_MAXCONNECTS, 50);
        $this->logger = $logger;
    }

    /**
     * Class destructor
     *
     */
    public function __destruct()
    {
        $this->multiCurlCloseAll();
    }

    /**
     * Close all active curl handlers and multi curl handler itself
     *
     */
    public function multiCurlCloseAll(): void
    {
        if ($this->multi_handler != null) {
            foreach ($this->curl_handlers as $curl_handler) {
                curl_multi_remove_handle($this->multi_handler, $curl_handler);
                if ($curl_handler != null) {
                    curl_close($curl_handler);
                    $curl_handler = null;
                }
            }
            curl_multi_close($this->multi_handler);
            $this->multi_handler = null;
        }
    }

    /**
     * Execute all curl in multiple parallel calls,
     * Run the sub-connections of the current cURL handle and store the results
     * to a container
     *
     * @throws RuntimeException
     */
    public function multiExec(): void
    {
        if ($this->multi_handler === null) {
            throw new RuntimeException('Multi curl handler is not initialized');
        }

        $_info = [];

        do {
            curl_multi_exec($this->multi_handler, $still_running);
            curl_multi_select($this->multi_handler); //Prevent eating CPU

            /*
             * curl_errno doesn't return any value in case of error (always 0)
             * We need to call curl_multi_info_read
             */
            if (($info = curl_multi_info_read($this->multi_handler)) !== false) {
                //Strict standards: resource ID#16 used as offset, casting to integer (16)
                $_info[spl_object_id($info['handle'])] = $info;
            }
        } while ($still_running > 0);

        foreach ($this->curl_handlers as $tokenHash => $curl_resource) {
            $this->multi_curl_results[$tokenHash] = curl_multi_getcontent($curl_resource);
            $this->multi_curl_info[$tokenHash]['curlinfo_total_time'] = curl_getinfo($curl_resource, CURLINFO_TOTAL_TIME);
            $this->multi_curl_info[$tokenHash]['curlinfo_connect_time'] = curl_getinfo($curl_resource, CURLINFO_CONNECT_TIME);
            $this->multi_curl_info[$tokenHash]['curlinfo_pretransfer_time'] = curl_getinfo($curl_resource, CURLINFO_PRETRANSFER_TIME);
            $this->multi_curl_info[$tokenHash]['curlinfo_start_transfer_time'] = curl_getinfo($curl_resource, CURLINFO_STARTTRANSFER_TIME);
            $this->multi_curl_info[$tokenHash]['curlinfo_effective_url'] = curl_getinfo($curl_resource, CURLINFO_EFFECTIVE_URL);
            $this->multi_curl_info[$tokenHash]['curlinfo_size_upload'] = curl_getinfo($curl_resource, CURLINFO_SIZE_UPLOAD);
            $this->multi_curl_info[$tokenHash]['curlinfo_size_download'] = curl_getinfo($curl_resource, CURLINFO_SIZE_DOWNLOAD);
            $this->multi_curl_info[$tokenHash]['curlinfo_header_size'] = curl_getinfo($curl_resource, CURLINFO_HEADER_SIZE);
            $this->multi_curl_info[$tokenHash]['curlinfo_header_out'] = curl_getinfo($curl_resource, CURLINFO_HEADER_OUT);
            $this->multi_curl_info[$tokenHash]['http_code'] = curl_getinfo($curl_resource, CURLINFO_HTTP_CODE);
            $this->multi_curl_info[$tokenHash]['primary_ip'] = curl_getinfo($curl_resource, CURLINFO_PRIMARY_IP);
            $this->multi_curl_info[$tokenHash]['error'] = curl_error($curl_resource);
            $this->multi_curl_info[$tokenHash]['transfer_time'] = round(
                (
                    $this->multi_curl_info[$tokenHash]['curlinfo_total_time'] -
                    $this->multi_curl_info[$tokenHash]['curlinfo_start_transfer_time']
                ),
                5
            );

            //Strict standards: resource ID#16 used as offset, casting to integer (16)
            $this->multi_curl_info[$tokenHash]['errno'] = @$_info[spl_object_id($curl_resource)]['result'];

            //HEADERS
            if (isset($this->curl_headers_requests[$tokenHash])) {
                $header = substr($this->multi_curl_results[$tokenHash], 0, $this->multi_curl_info[$tokenHash]['curlinfo_header_size']);
                $header = explode("\r\n", $header);
                $this->multi_curl_results[$tokenHash] = substr(
                    $this->multi_curl_results[$tokenHash],
                    $this->multi_curl_info[$tokenHash]['curlinfo_header_size']
                );
                $this->curl_headers_requests[$tokenHash] = $header;
            }

            //TIMING nad LOGGING
            $this->multi_curl_log[$tokenHash] = [];
            $this->multi_curl_log[$tokenHash]['timing'] = [
                'Total Time' => $this->multi_curl_info[$tokenHash]['curlinfo_total_time'],
                'Connect Time' => $this->multi_curl_info[$tokenHash]['curlinfo_connect_time'],
                'Pre-Transfer Time' => $this->multi_curl_info[$tokenHash]['curlinfo_pretransfer_time'],
                'Start Transfer Time' => $this->multi_curl_info[$tokenHash]['curlinfo_start_transfer_time'],
                'Transfer Time' => round(
                        (
                            (
                                $this->multi_curl_info[$tokenHash]['curlinfo_total_time'] -
                                $this->multi_curl_info[$tokenHash]['curlinfo_start_transfer_time']
                            ) * 1000000)
                    ) . "μs"
            ];

            $this->multi_curl_log[$tokenHash]["resource_hash"] = $tokenHash;
            $this->multi_curl_log[$tokenHash]["url"] = $this->multi_curl_info[$tokenHash]['curlinfo_effective_url'];

            if ($this->high_verbosity) {
                $this->multi_curl_log[$tokenHash]['options'] = $this->curl_options_requests[$tokenHash] ?? null;
                $this->multi_curl_log[$tokenHash]['options']["post_parameters"] = $this->curl_options_requests[$tokenHash][CURLOPT_POSTFIELDS] ?? null;
                unset($this->multi_curl_info[$tokenHash]['logging']['options'][CURLOPT_POSTFIELDS]);
            } else {
                $this->multi_curl_log[$tokenHash]['options']["post_parameters"] = $this->curl_options_requests[$tokenHash][CURLOPT_POSTFIELDS] ?? null;
            }

            if ($this->hasError($tokenHash)) {
                $this->multi_curl_log[$tokenHash]["error"] = $this->getError($tokenHash);
                $this->multi_curl_log[$tokenHash]["error_body"] = $this->getSingleContent($tokenHash);
            }
            //TIMING nad LOGGING

            if ($this->verbose) {
                $this->log($this->multi_curl_log[$tokenHash]);
            }
        }
    }

    protected function log(mixed $logging): void
    {
        if (!empty($this->logger)) {
            $this->logger->debug($logging);

            return;
        }
        LoggerFactory::doJsonLog($logging);
    }

    /**
     * Explicitly set that we want the response header for this token
     *
     * @param string $tokenHash
     *
     * @return $this
     */
    public function setRequestHeader(string $tokenHash): MultiCurlHandler
    {
        $resource = $this->curl_handlers[$tokenHash];
        curl_setopt($resource, CURLOPT_HEADER, true);
        $this->curl_headers_requests[$tokenHash] = true;

        return $this;
    }

    /**
     * Get the response header for the requested token
     *
     * @param string $tokenHash
     *
      * @return string[]
     */
    public function getSingleHeader(string $tokenHash): array
    {
        $headers = $this->curl_headers_requests[$tokenHash] ?? [];

        return is_array($headers) ? $headers : [];
    }

    /**
     * Get the response header for the requested token
     *
     * @return array<string, true|string[]>
     */
    public function getAllHeaders(): array
    {
        return $this->curl_headers_requests;
    }

    /**
     * Create a curl resource and add it to the pool indexing it with a unique identifier
     *
     * @param string $url string
     * @param array|null $options array
     * @param string|null $tokenHash string
     *
     * @return string|null Curl identifier
     * @throws RuntimeException
     */
    public function createResource(string $url, ?array $options = [], ?string $tokenHash = null): ?string
    {
        if ($tokenHash === null) {
            $tokenHash = md5(uniqid("", true));
        }

        $curl_resource = curl_init();

        curl_setopt($curl_resource, CURLOPT_URL, $url);
        @curl_setopt_array($curl_resource, $options);

        $this->curl_options_requests[$tokenHash] = $options;

        return $this->addResource($curl_resource, $tokenHash);
    }

    /**
     * Add an already existent curl resource to the pool indexing it with a unique identifier
     *
     * @param CurlHandle $curl_resource
     * @param null|string $tokenHash
     *
     * @return string|null
     * @throws RuntimeException
     */
    public function addResource(CurlHandle $curl_resource, ?string $tokenHash = null): ?string
    {
        if ($tokenHash === null) {
            $tokenHash = md5(uniqid('', true));
        }

        if ($this->multi_handler === null) {
            throw new RuntimeException('Multi curl handler is not initialized');
        }

        curl_multi_add_handle($this->multi_handler, $curl_resource);
        $this->curl_handlers[$tokenHash] = $curl_resource;

        return $tokenHash;
    }

    /**
     * Return all server responses
     *
     * @param callable|null $function
     *
     * @return mixed
     */
    public function getAllContents(?callable $function = null): mixed
    {
        return $this->_callbackExecute($this->multi_curl_results, $function);
    }

    /**
     * Return all curl info
     *
     * @return array[]
     */
    public function getAllInfo(): array
    {
        return $this->multi_curl_info;
    }

    /**
     * Get single result content from responses array by it's unique Index
     *
     * @param string        $tokenHash
     *
     * @param callable|null $function
     *
     * @return string|bool|null
     */
    public function getSingleContent(string $tokenHash, ?callable $function = null): bool|string|null
    {
        if (array_key_exists($tokenHash, $this->multi_curl_results)) {
            return $this->_callbackExecute($this->multi_curl_results[$tokenHash], $function);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSingleLog(string $tokenHash): ?array
    {
        return @$this->multi_curl_log[$tokenHash];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAllLogs(): array
    {
        return $this->multi_curl_log;
    }

    /**
     * Get single info from curl handlers array by its unique Index
     *
     * @param string $tokenHash
     *
     * @return array<string, mixed>|null
     */
    public function getSingleInfo(string $tokenHash): ?array
    {
        return $this->multi_curl_info[$tokenHash] ?? null;
    }

    /**
     * @return array<int, mixed>|null
     */
    public function getOptionRequest(string $tokenHash): ?array
    {
        return $this->curl_options_requests[$tokenHash] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getError(string $tokenHash): array
    {
        $res = [];
        $res['http_code'] = $this->multi_curl_info[$tokenHash]['http_code'];
        $res['error'] = $this->multi_curl_info[$tokenHash]['error'];
        $res['errno'] = $this->multi_curl_info[$tokenHash]['errno'];

        return $res;
    }

    /**
     * Check for error in curl resource by passing its unique index
     *
     * @param string $tokenHash
     *
     * @return bool
     */
    public function hasError(string $tokenHash): bool
    {
        return (!empty($this->multi_curl_info[$tokenHash]['error']) && $this->multi_curl_info[$tokenHash]['errno'] != 0) || (int)$this->multi_curl_info[$tokenHash]['http_code'] >= 400;
    }

    /**
     * Returns an array with errors on each resource. Returns empty array in case of no errors.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getErrors(): array
    {
        $map = array_map(function (string $tokenHash) {
            if ($this->hasError($tokenHash)) {
                return $this->getError($tokenHash);
            }

            return null;
        }, array_keys($this->multi_curl_info));

        return array_filter($map);  // <- remove null array entries
    }

    public function clear(): void
    {
        $this->multiCurlCloseAll();
        $this->curl_headers_requests = [];
        $this->curl_options_requests = [];
        $this->multi_curl_results = [];
        $this->multi_curl_info = [];
        $this->multi_curl_log = [];
    }

    protected function _callbackExecute(mixed $record, ?callable $function = null): mixed
    {
        if (is_callable($function)) {
            $is_array = is_array($record);
            if (!$is_array) {
                $record = [$record];
            }

            $record = array_map($function, $record);

            if (!$is_array) {
                $record = $record[0];
            }
        }

        return $record;
    }

} 
