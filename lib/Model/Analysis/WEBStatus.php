<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 04/05/15
 * Time: 13.37
 *
 */
class Analysis_WEBStatus extends Analysis_AbstractStatus {

    protected $_data_struct = array(
        'jobs'    => array(),
        'summary' =>
            array(
                "IN_QUEUE_BEFORE"         => 0, "IN_QUEUE_BEFORE_PRINT" => "0", "STATUS" => "",
                "TOTAL_SEGMENTS"          => 0, "SEGMENTS_ANALYZED" => 0, "TOTAL_SEGMENTS_PRINT" => 0,
                "SEGMENTS_ANALYZED_PRINT" => 0,
                "TOTAL_FAST_WC"           => 0, "TOTAL_TM_WC" => 0, "TOTAL_FAST_WC_PRINT" => "0",
                "TOTAL_STANDARD_WC"       => 0, "TOTAL_STANDARD_WC_PRINT" => "0",
                "TOTAL_TM_WC_PRINT"       => "0",
                "STANDARD_WC_TIME"        => 0, "FAST_WC_TIME" => 0, "TM_WC_TIME" => 0,
                "STANDARD_WC_UNIT"        => "", "TM_WC_UNIT" => "", "FAST_WC_UNIT" => "",
                "USAGE_FEE"               => 0.00,
                "PRICE_PER_WORD"          => 0.00, "DISCOUNT" => 0.00
            )
    );

    public function getResult(){

        $this->result[ 'data' ][ 'summary' ][ 'IN_QUEUE_BEFORE_PRINT' ]   = number_format( $this->_globals[ 'IN_QUEUE_BEFORE' ], 0, ".", "," );
        $this->result[ 'data' ][ 'summary' ][ 'TOTAL_SEGMENTS_PRINT' ]    = number_format( $this->_globals[ 'TOTAL_SEGMENTS' ], 0, ".", "," );
        $this->result[ 'data' ][ 'summary' ][ 'SEGMENTS_ANALYZED_PRINT' ] = number_format( $this->_globals[ 'SEGMENTS_ANALYZED' ], 0, ".", "," );
        $this->result[ 'data' ][ 'summary' ][ 'TOTAL_STANDARD_WC_PRINT' ] = number_format( $this->_globals[ 'TOTAL_STANDARD_WC' ], 0, ".", "," );
        $this->result[ 'data' ][ 'summary' ][ 'TOTAL_FAST_WC_PRINT' ]     = number_format( $this->_globals[ 'TOTAL_FAST_WC' ], 0, ".", "," );
        $this->result[ 'data' ][ 'summary' ][ 'TOTAL_TM_WC_PRINT' ]       = number_format( $this->_globals[ 'TOTAL_TM_WC' ], 0, ".", "," );
        $this->result[ 'data' ][ 'summary' ][ 'TOTAL_RAW_WC_PRINT' ]      = number_format( $this->_globals[ 'TOTAL_RAW_WC' ], 0, ".", "," );
        $this->result[ 'data' ][ 'summary' ][ 'TOTAL_PAYABLE_PRINT' ]     = number_format( $this->_globals[ 'TOTAL_PAYABLE' ], 0, ".", "," );

        return $this->result;

    }

}