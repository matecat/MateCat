<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 29/06/2017
 * Time: 17:41
 */

namespace Features\Dqf\Model\CachedAttributes ;

class Severity extends AbstractCachedAttribute {

    protected $resource_name = 'severity' ;

    public $mappedNames = [] ;

    public function __construct() {
        parent::__construct() ;
    }

    public function getSeveritiesForReviewSettings() {
        return array_map(function( $severity ) {

            return [
                    'severityId' => $severity['id'],
                    'weight'     => $severity['defaultValue']
            ];

        }, $this->resource_json ) ;
    }

    public function demapName( $name ) {
        $found = array_filter( $this->resource_json, function( $severity ) use ( $name ) {
            return $severity[ 'name' ] == $name ;
        }) ;
        return end( $found )['id'] ;
    }

    public function getSortedDqfIds() {
        $ids = array_map(function( $severity ) {
            return $severity['id'] ;
        }, $this->resource_json ) ;

        sort( $ids, SORT_NUMERIC ) ;

        return $ids ;
    }
}