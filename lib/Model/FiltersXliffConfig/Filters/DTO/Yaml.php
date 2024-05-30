<?php

namespace FiltersXliffConfig\Filters\DTO;

use JsonSerializable;

class Yaml implements JsonSerializable
{
    private $extract_arrays;
    private $translate_keys;
    private $do_not_translate_keys;

    /**
     * @param bool|null $extract_arrays
     */
    public function setExtractArrays(?bool $extract_arrays): void
    {
        $this->extract_arrays = $extract_arrays;
    }

    /**
     * @param array $translate_keys
     */
    public function setTranslateKeys(array $translate_keys): void
    {
        $this->translate_keys = $translate_keys;
    }

    /**
     * @param array $do_not_translate_keys
     */
    public function setDoNotTranslateKeys(array $do_not_translate_keys): void
    {
        $this->do_not_translate_keys = $do_not_translate_keys;
    }

    /**
     * @param $data
     */
    public function fromArray($data)
    {
        if(isset($data['extract_arrays'])){
            $this->setExtractArrays($data['extract_arrays']);
        }

        if(isset($data['translate_keys'])){
            $this->setTranslateKeys($data['translate_keys']);
        }

        if(isset($data['do_not_translate_keys'])){
            $this->setDoNotTranslateKeys($data['do_not_translate_keys']);
        }
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'extract_arrays' => $this->extract_arrays,
            'translate_keys' => $this->translate_keys,
            'do_not_translate_keys' => $this->do_not_translate_keys,
        ];
    }
}