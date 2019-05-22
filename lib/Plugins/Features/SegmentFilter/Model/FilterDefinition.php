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

    private $custom_conditions_sql  = [] ;
    private $custom_conditions_data = [] ;

    /**
     * @param array $filter_data
     */
    public function __construct( array $filter_data ) {
        $this->filter_data = $filter_data;
    }

    public function isRevision(){
        return !empty( $this->filter_data['revision'] ) && $this->filter_data['revision'] == 1;
    }

    public function revisionNumber() {
        if ( in_array( $this->getSegmentStatus(), \Constants_TranslationStatus::$REVISION_STATUSES ) ) {
            if ( empty( $this->filter_data['revision_number']) ) {
                $this->filter_data['revision_number'] = 1 ;
            }
            return $this->filter_data['revision_number'] ;
        }
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
        return @$this->filter_data['sample']['size'];
    }

    public function getSegmentStatus() {
        return strtoupper( $this->filter_data['status'] );
    }

    public function isValid() {
        // TODO: validate revision number
        return ( $this->isSampled() || $this->getSegmentStatus() != '' ) ;
    }

    public function setCustomCondition( $condition, $data ) {
        $this->custom_conditions_sql [] = $condition ;
        array_merge( $this->custom_conditions_data, $data) ;
    }

    public function hasCustomCondition() {
        return !empty( $this->custom_conditions_sql );
    }

    public function getCustomConditionSQL() {
        return implode(" AND ", $this->custom_conditions_sql ) ;
    }

    public function getCustomConditionData() {
        return $this->custom_conditions_data ;
    }

}