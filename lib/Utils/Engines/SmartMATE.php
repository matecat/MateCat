<?php

namespace Utils\Engines;

use Exception;
use Model\Engines\Structs\SmartMATEStruct;
use TypeError;
use Utils\Constants\EngineConstants;
use Utils\Engines\Traits\Oauth;
use Utils\Engines\Results\TMSAbstractResponse;

/**
 * Created by PhpStorm.
 * @property string|null $client_id
 * @property string|null $client_secret
 * @property string|null $oauth_url
 * @property string|null $token
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/03/15
 * Time: 12.10
 *
 */
class SmartMATE extends AbstractEngine
{

    use Oauth {
        get as private oauthGet;
        _authenticate as private oauthAuthenticate;
        getAuthParameters as private oauthGetAuthParameters;
    }

    /** @var array<string, mixed> */
    protected array $_auth_parameters = [
        'client_id' => null,
        'client_secret' => null,

        /**
         * Hardcoded params, from documentation
         * @see https://mt.smartmate.co/translate
         */
        'grant_type' => "client_credentials",
        'scope' => "translate"
    ];

    protected array $_config = [
        'segment' => null,
        'translation' => null,
        'source' => null,
        'target' => null,
    ];

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function __construct($engineRecord)
    {
        parent::__construct($engineRecord);
        if ($this->getEngineRecord()->type != EngineConstants::MT) {
            throw new Exception("Engine {$this->getEngineRecord()->id} is not a MT engine, found {$this->getEngineRecord()->type} -> {$this->getEngineRecord()->class_load}");
        }
    }

    protected function _fixLangCode(string $lang): string
    {
        $l = explode("-", strtolower(trim($lang)));

        return $l[0];
    }

    /**
     * @return array<int, mixed>
     */
    protected function getAuthParameters(): array
    {
        return $this->oauthGetAuthParameters();
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    protected function _authenticate(): void
    {
        $this->oauthAuthenticate();
    }

    /**
     * @param array<string, mixed> $objResponse
     *
     * @return array<string, mixed>
     */
    protected function _formatAuthenticateError(array $objResponse): array
    {
        //format as a normal Translate Response and send to decoder to output the data
        $errorResponse = $objResponse['error']['response'] ?? null;
        $decodedResponse = is_string($errorResponse) ? json_decode($errorResponse) : null;
        $objResponse['error_description'] = (is_object($decodedResponse) && isset($decodedResponse->error)) ? (string)$decodedResponse->error : '';

        return $objResponse;
    }

    /**
     * @throws Exception
     */
    protected function _decode(mixed $rawValue, array $parameters = [], $function = null): array
    {
        if (is_string($rawValue)) {
            $decoded = json_decode($rawValue, true);
            $translation = is_array($decoded) ? (string)($decoded['translation'] ?? '') : '';
            $decoded = [
                'data' => [
                    "translations" => [
                        ['translatedText' => $translation]
                    ]
                ]
            ];

            return $this->_composeMTResponseAsMatch((string)($parameters['text'] ?? ''), $decoded);
        } else {
            if (is_array($rawValue) && ($rawValue['error']['code'] ?? null) == 0 && ($rawValue['responseStatus'] ?? 0) >= 400) {
                $rawValue['error']['code'] = -$rawValue['responseStatus'];
            }

            $this->logger->debug($rawValue);

            return $rawValue; // already decoded in case of error
        }
    }

    protected function _getEngineStruct(): SmartMATEStruct
    {
        return SmartMATEStruct::getStruct();
    }

    protected function _setTokenEndLife(?int $expires_in_seconds = null): void
    {
        if (!is_null($expires_in_seconds)) {
            $this->token_endlife = $expires_in_seconds;

            return;
        }

        /**
         * Gain 2 minutes to not fall back into a recursion
         *
         * @see static::get
         */
        $this->token_endlife = time() + 3480;
    }

    protected function _checkAuthFailure(): bool
    {
        $message = (string)($this->result['error']['message']);
        $expiration = (stripos($message, 'token is expired') !== false);
        $auth_failure = $this->result['error']['code'] < 0;

        return $expiration || $auth_failure;
    }

    /**
     * @param array<string, mixed> $_config
     *
     * @return array<string, mixed>|TMSAbstractResponse
     * @throws Exception
     */
    public function get(array $_config): TMSAbstractResponse|array
    {
        return $this->oauthGet($_config);
    }

    /**
     * @param mixed $_config
     */
    public function set($_config): bool
    {
        // SmartMATE does not have this method
        return true;
    }

    /**
     * @param mixed $_config
     */
    public function update($_config): bool
    {
        // SmartMATE does not have this method
        return true;
    }

    /**
     * @param mixed $_config
     */
    public function delete($_config): bool
    {
        // SmartMATE does not have this method
        return true;
    }

    /**
     * @throws Exception
     *
     * @return array<string, mixed>
     */
    protected function _formatRecursionError(): array
    {
        return $this->_composeMTResponseAsMatch(
            '',
            [
                'error' => [
                    'code' => -499,
                    'message' => "Client Closed Get",
                    'response' => 'Maximum recursion limit reached'
                    // Some useful info might still be contained in the response body
                ],
                'responseStatus' => 499
            ] //return negative number
        );
    }

    /**
     * @param array<string, mixed> $_config
     *
     * @return array<string, mixed>
     */
    protected function _fillCallParameters(array $_config): array
    {
        $parameters = [];
        $parameters['text'] = $_config['segment'];
        $parameters['from'] = $_config['source'];
        $parameters['to'] = $_config['target'];

        return $parameters;
    }

    /**
     * @inheritDoc
     */
    public static function getConfigurationParameters(): array
    {
        return [
            'enable_mt_analysis',
        ];
    }
}
