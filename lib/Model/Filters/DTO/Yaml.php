<?php

namespace Filters\DTO;

use Countable;
use JsonSerializable;

class Yaml implements IDto, JsonSerializable, Countable {

    private bool  $extract_arrays        = false;
    private array $translate_keys        = [];
    private array $do_not_translate_keys = [];

    /**
     * @param bool|null $extract_arrays
     */
    public function setExtractArrays( bool $extract_arrays ): void {
        $this->extract_arrays = $extract_arrays;
    }

    /**
     * @param array $translate_keys
     */
    public function setTranslateKeys( array $translate_keys ): void {
        $this->translate_keys = $translate_keys;
    }

    /**
     * @param array $do_not_translate_keys
     */
    public function setDoNotTranslateKeys( array $do_not_translate_keys ): void {
        $this->do_not_translate_keys = $do_not_translate_keys;
    }

    /**
     * @param $data
     */
    public function fromArray( $data ) {
        if ( isset( $data[ 'extract_arrays' ] ) ) {
            $this->setExtractArrays( $data[ 'extract_arrays' ] );
        }

        if ( isset( $data[ 'translate_keys' ] ) ) {
            $this->setTranslateKeys( $data[ 'translate_keys' ] );
        }

        if ( isset( $data[ 'do_not_translate_keys' ] ) ) {
            $this->setDoNotTranslateKeys( $data[ 'do_not_translate_keys' ] );
        }
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize() {

        $format = [];

        $format[ 'extract_arrays' ] = $this->extract_arrays;
        $format[ 'translate_keys' ] = $this->translate_keys;

        if ( !empty( $this->do_not_translate_keys ) ) {
            $format[ 'do_not_translate_keys' ] = $this->do_not_translate_keys;
            unset( $format[ 'translate_keys' ] );
        }

        return $format;

    }

    /**
     * @return int
     */
    public function count(): int {
        return count( $this->jsonSerialize() );
    }

}