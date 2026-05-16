<?php

namespace Utils\Engines;

use Exception;
use TypeError;
use Utils\Constants\EngineConstants;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;

/**
 * Created by PhpStorm.
 * @property string $client_secret
 * @author egomez-prompsit egomez@prompsit.com
 * Date: 29/07/15
 * Time: 12.17
 *
 */
class Apertium extends AbstractEngine
{

    protected array $_config = [
        'segment' => null,
        'source' => null,
        'target' => null,
        'key' => null,
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
     * @param string $lang
     *
     * @return string
     */
    protected function _fixLangCode(string $lang): string
    {
        return $lang;
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
        $original = ['text' => ''];
        $all_args = func_get_args();

        if (is_string($rawValue)) {
            $original = json_decode($all_args[1]["data"], true);
            $decoded = json_decode($rawValue, true);
            $decoded = [
                'data' => [
                    "translations" => [
                        ['translatedText' => $decoded["text"]]
                    ]
                ]
            ];
        } else {
            $decoded = $rawValue; // already decoded in case of error
        }

        return $this->_composeMTResponseAsMatch($original["text"], $decoded);
    }

    /**
     * @param array<string, mixed> $_config
     * @throws TypeError
     * @throws \Psr\Log\InvalidArgumentException
     * @throws \RuntimeException
     */
    public function get(array $_config): GetMemoryResponse
    {
        $param_data = json_encode([
            "mtsystem" => "apertium",
            "src" => $_config['source'],
            "trg" => $_config['target'],
            "text" => $_config['segment']
        ]);

        $parameters = [];
        if ($this->client_secret != '' && $this->client_secret != null) {
            $parameters['key'] = $this->client_secret;
        }
        $parameters['func'] = "translate";
        $parameters['data'] = $param_data;

        $this->_setAdditionalCurlParams([
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true
            ]
        );
        $this->call("translate_relative_url", $parameters, false);

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
