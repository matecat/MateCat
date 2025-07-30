<?php

namespace Filters\DTO;

use JsonSerializable;

class Xml implements IDto, JsonSerializable {

    private bool  $preserve_whitespace       = false;
    private array $translate_elements        = [];
    private array $do_not_translate_elements = [];
    private array $translate_attributes      = [];

    /**
     * @param bool|null $preserve_whitespace
     */
    public function setPreserveWhitespace( bool $preserve_whitespace ): void {
        $this->preserve_whitespace = $preserve_whitespace;
    }

    /**
     * @param array $translate_elements
     */
    public function setTranslateElements( array $translate_elements ): void {
        $this->translate_elements = $translate_elements;
    }

    /**
     * @param array $do_not_translate_elements
     */
    public function setDoNotTranslateElements( array $do_not_translate_elements ): void {
        $this->do_not_translate_elements = $do_not_translate_elements;
    }

    /**
     * @param array $translate_attributes
     */
    public function setTranslateAttributes( array $translate_attributes ): void {
        $this->translate_attributes = $translate_attributes;
    }

    /**
     * @param $data
     */
    public function fromArray( $data ) {
        if ( isset( $data[ 'preserve_whitespace' ] ) ) {
            $this->setPreserveWhitespace( $data[ 'preserve_whitespace' ] );
        }

        if ( isset( $data[ 'translate_elements' ] ) ) {
            $this->setTranslateElements( $data[ 'translate_elements' ] );
        }

        if ( isset( $data[ 'do_not_translate_elements' ] ) ) {
            $this->setDoNotTranslateElements( $data[ 'do_not_translate_elements' ] );
        }

        if ( isset( $data[ 'translate_attributes' ] ) ) {
            $this->setTranslateAttributes( $data[ 'translate_attributes' ] );
        }
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize() {

        $format = [];

        $format[ 'preserve_whitespace' ] = $this->preserve_whitespace;
        $format[ 'translate_elements' ]  = $this->translate_elements;

        if ( !empty( $this->do_not_translate_elements ) ) {
            $format[ 'do_not_translate_elements' ] = $this->do_not_translate_elements;
            unset( $format[ 'translate_elements' ] );
        }

        $format[ 'translate_attributes' ] = $this->translate_attributes;

        return $format;

    }

}