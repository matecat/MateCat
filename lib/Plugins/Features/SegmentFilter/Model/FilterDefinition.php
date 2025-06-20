<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/10/16
 * Time: 11:20 AM
 */

namespace Features\SegmentFilter\Model;


class FilterDefinition {

    /**
     * @var array
     */
    private array $filter_data;

    /**
     * @param array $filter_data
     */
    public function __construct( array $filter_data ) {
        $this->filter_data = $filter_data;
    }

    public function isRevision(): bool {
        return !empty( $this->filter_data[ 'revision' ] ) && $this->filter_data[ 'revision' ] == 1;
    }

    public function isSampled(): bool {
        return array_key_exists( 'sample', $this->filter_data ) && $this->filter_data[ 'sample' ] == true;
    }

    public function isFiltered(): bool {
        return !empty( $this->filter_data[ 'status' ] );
    }

    public function sampleData(): array {
        return $this->filter_data[ 'sample' ] ?? [];
    }

    public function sampleType(): string {
        return $this->filter_data[ 'sample' ][ 'type' ] ?? '';
    }

    public function sampleSize(): int {
        return $this->filter_data[ 'sample' ][ 'size' ] ?? 0;
    }

    public function getSegmentStatus(): string {
        return strtoupper( $this->filter_data[ 'status' ] );
    }

    public function isValid(): bool {
        return ( $this->isSampled() || $this->getSegmentStatus() != '' );
    }

}