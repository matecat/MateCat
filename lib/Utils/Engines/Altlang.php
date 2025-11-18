<?php

namespace Utils\Engines;

use Controller\API\Commons\Exceptions\AuthenticationError;
use Exception;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Utils\Constants\EngineConstants;
use Utils\Engines\Results\TMSAbstractResponse;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;

/**
 * Created by PhpStorm.
 * @property string client_secret
 * @author egomez-prompsit egomez@prompsit.com
 * Date: 29/07/15
 * Time: 12.17
 *
 */
class Altlang extends AbstractEngine
{

    protected array $_config = [
        'segment' => null,
        'source' => null,
        'target' => null,
        'key' => null,
    ];

    /**
     * @throws Exception
     */
    public function __construct($engineRecord)
    {
        parent::__construct($engineRecord);
        if ($this->getEngineRecord()->type != EngineConstants::MT) {
            throw new Exception(
                "Engine {$this->getEngineRecord()->id} is not a MT engine, found {$this->getEngineRecord()->type} -> {$this->getEngineRecord()->class_load}"
            );
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
     * @param array $parameters
     * @param null $function
     *
     * @return array
     * @throws Exception
     */
    protected function _decode(mixed $rawValue, array $parameters = [], $function = null): array
    {
        $all_args = func_get_args();

        if (is_string($rawValue)) {
            $original = json_decode($all_args[1]["data"] ?? '', true);
            $decoded = json_decode($rawValue, true);

            if (isset($decoded['error'])) {
                return $this->_composeMTResponseAsMatch('', [
                    'error' => [
                        'message' => $decoded['error'],
                        'code' => -1
                    ]
                ]); // error
            }

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

        return $this->_composeMTResponseAsMatch($original["text"] ?? '', $decoded);
    }

    /**
     * @param array $_config
     *
     * @return array|TMSAbstractResponse|void
     * @throws AuthenticationError
     * @throws NotFoundException
     * @throws ValidationError
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws Exception
     */
    public function get(array $_config)
    {
        // Fallback on MyMemory in case of not supported source/target combination
        if (!$this->checkLanguageCombination($_config['source'], $_config['target'])) {
            /** @var MyMemory $myMemory */
            $myMemory = EnginesFactory::getInstance(1);

            $result = $myMemory->get($_config);
            $this->result = $result->get_matches_as_array();

            return $this->result;
        }

        $parameters = [
            'func' => 'translate',
            "mtsystem" => "apertium",
            "context" => "altlang",
            "src" => $this->convertLanguageCode($_config['source']),
            "trg" => $this->convertLanguageCode($_config['target']),
            "text" => $_config['segment']
        ];

        if ($this->client_secret != '' && $this->client_secret != null) {
            $parameters['key'] = $this->client_secret;
        }

        $this->_setAdditionalCurlParams([
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);

        $this->call("translate_relative_url", $parameters, true, true);

        // fix missing info
        if (empty($this->result['raw_segment'])) {
            $this->result['raw_segment'] = $_config['segment'];
        }

        if (empty($this->result['segment'])) {
            $this->result['segment'] = $_config['segment'];
        }

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
     * @param string $code
     *
     * @return mixed
     */
    private function convertLanguageCode(string $code)
    {
        $code = str_replace("-", "_", $code);
        $code = str_replace("es_AR", "es_LA", $code);
        $code = str_replace("es_CO", "es_LA", $code);
        $code = str_replace("es_419", "es_LA", $code);
        $code = str_replace("es_MX", "es_LA", $code);
        $code = str_replace("es_US", "es_LA", $code);

        return $code;
    }

    /**
     * @param string $source
     * @param string $target
     *
     * @return bool
     */
    private function checkLanguageCombination(string $source, string $target): bool
    {
        $supportedCombinations = [
            ['pt_BR' => 'pt_PT'],
            ['pt_PT' => 'pt_BR'],
            ['fr_CA' => 'fr_FR'],
            ['fr_FR' => 'fr_CA'],
            ['en_US' => 'en_GB'],
            ['en_GB' => 'en_US'],
            ['es_LA' => 'es_ES'],
            ['es_ES' => 'es_LA'],
        ];

        $combination = [
            $this->convertLanguageCode($source) => $this->convertLanguageCode($target)
        ];

        return in_array($combination, $supportedCombinations);
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