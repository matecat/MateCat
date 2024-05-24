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
     * Json constructor.
     * @param bool|null $extract_arrays
     * @param bool|null $escape_forward_slashes
     * @param array $translate_keys
     * @param array $do_not_translate_keys
     * @param array $context_keys
     */
    public function __construct(
        ?bool $extract_arrays = null,
        ?bool $escape_forward_slashes = null,
        array $translate_keys = [],
        array $do_not_translate_keys = [],
        array $context_keys = []
    )
    {
        $this->extract_arrays = $extract_arrays;
        $this->escape_forward_slashes = $escape_forward_slashes;
        $this->translate_keys = $translate_keys;
        $this->do_not_translate_keys = $do_not_translate_keys;
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