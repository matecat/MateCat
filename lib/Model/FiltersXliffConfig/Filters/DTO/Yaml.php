<?php

namespace FiltersXliffConfig\Filters\DTO;

use JsonSerializable;

class Yaml implements JsonSerializable
{
    private $extract_arrays;
    private $translate_keys;
    private $do_not_translate_keys;

    /**
     * Yaml constructor.
     * @param bool|null $extract_arrays
     * @param array $translate_keys
     * @param array $do_not_translate_keys
     */
    public function __construct(
        ?bool $extract_arrays = null,
        array $translate_keys = [],
        array $do_not_translate_keys = []
    )
    {
        $this->extract_arrays = $extract_arrays;
        $this->translate_keys = $translate_keys;
        $this->do_not_translate_keys = $do_not_translate_keys;
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