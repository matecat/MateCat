<?php

namespace FiltersXliffConfig\Filters\DTO;

use JsonSerializable;

class Xml implements JsonSerializable
{
    private $preserve_whitespace;
    private $translate_elements;
    private $do_not_translate_elements;
    private $include_attributes;

    /**
     * Xml constructor.
     * @param bool|null $preserve_whitespace
     * @param array $translate_elements
     * @param array $do_not_translate_elements
     * @param array $include_attributes
     */
    public function __construct(
        ?bool $preserve_whitespace = null,
        array $translate_elements = [],
        array $do_not_translate_elements = [],
        array $include_attributes = []
    )
    {
        $this->preserve_whitespace = $preserve_whitespace;
        $this->translate_elements = $translate_elements;
        $this->do_not_translate_elements = $do_not_translate_elements;
        $this->include_attributes = $include_attributes;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'preserve_whitespace' => $this->preserve_whitespace,
            'translate_elements' => $this->translate_elements,
            'do_not_translate_elements' => $this->do_not_translate_elements,
            'include_attributes' => $this->include_attributes,
        ];
    }
}