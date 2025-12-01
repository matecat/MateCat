<?php

namespace Utils\Engines;

use Exception;
use Utils\Constants\EngineConstants;

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
     * @param array $parameters
     * @param null $function
     *
     * @return array
     * @throws Exception
     */
    protected function _decode(mixed $rawValue, array $parameters = [], $function = null): array
    {
        $all_args = func_get_args();
        $all_args[1]['text'] = $all_args[1]['q'];

        if (is_string($rawValue)) {
            $decoded = json_decode($rawValue, true);
            if (isset($decoded["data"])) {
                return $this->_composeMTResponseAsMatch($all_args[1]['text'], $decoded);
            } else {
                $decoded = [
                    'error' => [
                        'code' => $decoded["code"],
                        'message' => $decoded["message"]
                    ]
                ];
            }
        } else {
            $resp = json_decode($rawValue["error"]["response"], true);
            if (isset($resp["error"]["code"]) && isset($resp["error"]["message"])) {
                $rawValue["error"]["code"] = $resp["error"]["code"];
                $rawValue["error"]["message"] = $resp["error"]["message"];
            }
            $decoded = $rawValue; // already decoded in case of error
        }

        return $decoded;
    }

    public function get(array $_config)
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

        return $this->result;
    }

    public function set($_config): bool
    {
        //if engine does not implement SET method, exit
        return true;
    }

    public function update($_config): bool
    {
        //if engine does not implement UPDATE method, exit
        return true;
    }

    public function delete($_config): bool
    {
        //if engine does not implement DELETE method, exit
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getConfigurationParameters(): array
    {
        return [
            'enable_mt_analysis',
        ];
    }

}
