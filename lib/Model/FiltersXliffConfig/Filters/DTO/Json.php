<?php

namespace FiltersXliffConfig\Filters\DTO;

use JsonSerializable;

class Json implements JsonSerializable
{
    private $extract_arrays;
    private $escape_forward_slashes;
    private $translate_keys;
    private $do_not_translate_keys;
    private $context_keys;

    /**
     * @param bool|null $extract_arrays
     */
    public function setExtractArrays(?bool $extract_arrays): void
    {
        $this->extract_arrays = $extract_arrays;
    }

    /**
     * @param bool|null $escape_forward_slashes
     */
    public function setEscapeForwardSlashes(?bool $escape_forward_slashes): void
    {
        $this->escape_forward_slashes = $escape_forward_slashes;
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
     * @param array $context_keys
     */
    public function setContextKeys(array $context_keys): void
    {
        $this->context_keys = $context_keys;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'extract_arrays' => $this->extract_arrays,
            'escape_forward_slashes' => $this->escape_forward_slashes,
            'translate_keys' => $this->translate_keys,
            'do_not_translate_keys' => $this->do_not_translate_keys,
            'context_keys' => $this->context_keys,
        ];
    }
}