<?php

namespace Utils\Engines;

use Exception;
use TypeError;
use Utils\Constants\EngineConstants;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;

/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 28/12/2017
 * Time: 17:25
 */
class GoogleTranslate extends AbstractEngine
{

    protected array $_config = [
        'q' => null,
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

    /**
     * @param mixed $rawValue
     * @param array<string, mixed> $parameters
     * @param null $function
     *
     * @return GetMemoryResponse
     * @throws Exception
     * @throws TypeError
     */
    protected function _decode(mixed $rawValue, array $parameters = [], $function = null): GetMemoryResponse
    {
        $all_args = func_get_args();
        $all_args[1]['text'] = $all_args[1]['q'];

        if (is_string($rawValue)) {
            $decoded = json_decode($rawValue, true);
            if (isset($decoded["data"])) {
                return $this->_composeMTResponseAsMatch($all_args[1]['text'], $decoded);
            } else {
                $engineResponse = json_decode($decoded['error']['response'], true);
                throw new Exception($engineResponse['error']['message'], $engineResponse['error']['code']);
            }
        } else {
            throw new Exception($rawValue['error']['message'], 500);  // already decoded in case of error
        }
    }

    /**
     * @param array<string, mixed> $_config
     * @throws TypeError
     * @throws \Psr\Log\InvalidArgumentException
     * @throws \RuntimeException
     */
    public function get(array $_config): GetMemoryResponse
    {
        $parameters = [];

        $parameters['key'] = $this->getEngineRecord()->getExtraParamsAsArray()['client_secret'] ?? null;
        $parameters['target'] = $this->_fixLangCode($_config['target']);
        $parameters['source'] = $this->_fixLangCode($_config['source']);
        $parameters['q'] = $_config['segment'];

        $this->_setAdditionalCurlParams(
            [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($parameters)
            ]
        );

        $this->call("translate_relative_url", $parameters, true);

        return $this->_getResultAsGetMemoryResponse();
    }

    /**
     * @param mixed $_config
     */
    public function set($_config): bool
    {
        //if engine does not implement SET method, exit
        return true;
    }

    /**
     * @param mixed $_config
     */
    public function update($_config): bool
    {
        //if engine does not implement UPDATE method, exit
        return true;
    }

    /**
     * @param mixed $_config
     */
    public function delete($_config): bool
    {
        //if engine does not implement DELETE method, exit
        return true;
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
