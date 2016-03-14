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
    private $filter_data;
    /**
     * @param array $fitler_data
     */

    public function __construct( array $filter_data ) {
        $this->filter_data = $filter_data;
    }

    public function isSampled() {
        return array_key_exists('sample', $this->filter_data) && $this->filter_data['sample'] == true;
    }

    public function isFiltered() {
        return !empty( $this->filter_data['status'] );
    }

    public function sampleData() {
        return $this->filter_data['sample'];
    }

    public function sampleType() {
        return $this->filter_data['sample']['type'];
    }

    public function sampleSize() {
        return $this->filter_data['sample']['size'];
    }

    public function getSegmentStatus() {
        return strtoupper( $this->filter_data['status'] );
    }

    public function isValid() {
        return ( $this->isSampled() || $this->getSegmentStatus() != '' ) ;
    }

}