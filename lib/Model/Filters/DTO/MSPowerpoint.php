<?php

namespace Filters\DTO;

use JsonSerializable;

class MSPowerpoint implements IDto, JsonSerializable {

    private bool  $extract_doc_properties = false;
    private bool  $extract_hidden_slides  = false;
    private bool  $extract_notes          = true;
    private array $translate_slides       = [];

    /**
     * @param bool|null $extract_doc_properties
     */
    public function setExtractDocProperties( bool $extract_doc_properties ): void {
        $this->extract_doc_properties = $extract_doc_properties;
    }

    /**
     * @param bool|null $extract_hidden_slides
     */
    public function setExtractHiddenSlides( bool $extract_hidden_slides ): void {
        $this->extract_hidden_slides = $extract_hidden_slides;
    }

    /**
     * @param bool|null $extract_notes
     */
    public function setExtractNotes( bool $extract_notes ): void {
        $this->extract_notes = $extract_notes;
    }

    /**
     * @param array $translate_slides
     */
    public function setTranslateSlides( array $translate_slides ): void {
        $this->translate_slides = $translate_slides;
    }

    /**
     * @param $data
     */
    public function fromArray( $data ) {
        if ( isset( $data[ 'extract_doc_properties' ] ) ) {
            $this->setExtractDocProperties( $data[ 'extract_doc_properties' ] );
        }

        if ( isset( $data[ 'extract_hidden_slides' ] ) ) {
            $this->setExtractHiddenSlides( $data[ 'extract_hidden_slides' ] );
        }

        if ( isset( $data[ 'translate_slides' ] ) ) {
            $this->setTranslateSlides( $data[ 'translate_slides' ] );
        }

        if ( isset( $data[ 'extract_notes' ] ) ) {
            $this->setExtractNotes( $data[ 'extract_notes' ] );
        }
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize() {

        $format = [];

        $format[ 'extract_doc_properties' ] = $this->extract_doc_properties;
        $format[ 'translate_slides' ]       = $this->translate_slides;
        $format[ 'extract_notes' ]          = $this->extract_notes;

        if ( !empty( $this->extract_hidden_slides ) ) {
            $format[ 'extract_hidden_slides' ] = $this->extract_hidden_slides;
            unset( $format[ 'translate_slides' ] );
        }

        return $format;

    }

}