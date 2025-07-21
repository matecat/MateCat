<?php

namespace Model\Filters\DTO;

use JsonSerializable;

class Dita implements IDto, JsonSerializable {

    private array $do_not_translate_elements = [];

    /**
     * @param array $do_not_translate_elements
     */
    public function setDoNotTranslateElements(array $do_not_translate_elements): void
    {
        $this->do_not_translate_elements = $do_not_translate_elements;
    }

    /**
     * @param $data
     */
    public function fromArray( $data ) {
        if ( isset( $data[ 'do_not_translate_elements' ] ) ) {
            $this->setDoNotTranslateElements( $data[ 'do_not_translate_elements' ] );
        }
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize() {
        return [
            'do_not_translate_elements' => $this->do_not_translate_elements
        ];
    }

}