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
     * @param bool|null $preserve_whitespace
     */
    public function setPreserveWhitespace(?bool $preserve_whitespace): void
    {
        $this->preserve_whitespace = $preserve_whitespace;
    }

    /**
     * @param array $translate_elements
     */
    public function setTranslateElements(array $translate_elements): void
    {
        $this->translate_elements = $translate_elements;
    }

    /**
     * @param array $do_not_translate_elements
     */
    public function setDoNotTranslateElements(array $do_not_translate_elements): void
    {
        $this->do_not_translate_elements = $do_not_translate_elements;
    }

    /**
     * @param array $include_attributes
     */
    public function setIncludeAttributes(array $include_attributes): void
    {
        $this->include_attributes = $include_attributes;
    }

    /**
     * @param $data
     */
    public function fromArray($data)
    {
        if(isset($data['preserve_whitespace'])){
            $this->setPreserveWhitespace($data['preserve_whitespace']);
        }

        if(isset($data['translate_elements'])){
            $this->setTranslateElements($data['translate_elements']);
        }

        if(isset($data['do_not_translate_elements'])){
            $this->setDoNotTranslateElements($data['do_not_translate_elements']);
        }

        if(isset($data['include_attributes'])){
            $this->setIncludeAttributes($data['include_attributes']);
        }
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