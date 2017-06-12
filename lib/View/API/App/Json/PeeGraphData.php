<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 12/06/17
 * Time: 16.36
 *
 */

namespace API\App\Json;


use DataAccess\ShapelessConcreteStruct;

class PeeGraphData {

    protected $data;

    /**
     * PeeGraphData constructor.
     *
     * @param ShapelessConcreteStruct[] $stats
     */
    public function __construct( array $stats ) {
        $this->data = $stats;
    }

    public function render() {


        $returnValue = [
                'dataSet' => [],
                'lines'    => []
        ];

        $dateStore = [] ;

        foreach( $this->data as $set ){

            if( !isset( $dateStore[ $set->date ] ) ){
                $dateStore[ $set->date ] = [];
            }

            $dateStore[ $set->date ][] = (int)$set->total_post_editing_effort;

            if( array_search( [ $set->source, $set->target, $set->fuzzy_band ], $returnValue[ 'lines' ] ) === false ){
                $returnValue[ 'lines' ][] = [ $set->source, $set->target, $set->fuzzy_band ];
            }

        }

        $count = 0;
        foreach( $dateStore as $key =>$value ) {
            $returnValue['dataSet'][$count++] = [ $key => $value ] ;
        }


        return $returnValue;

    }

}