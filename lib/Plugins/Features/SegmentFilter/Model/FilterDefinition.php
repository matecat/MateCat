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
        return array_key_exists('sampling', $this->filter_data);
    }

    public function getSegmentStatus() {
        return strtoupper( $this->filter_data['status'] );
    }

    public function isValid() {
        return ( $this->isSampled() || $this->getSegmentStatus() != '' ) ;
    }

}