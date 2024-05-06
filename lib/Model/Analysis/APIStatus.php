<?php
namespace Model\Analysis;

use Routes;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 04/05/15
 * Time: 13.37
 *
 * @deprecated old api model
 * @deprecated curl -X GET "https://dev.matecat.com/api/status?id_project=95&project_pass=c58749b32943" -H "accept: application/json"
 *
 */
class APIStatus extends AbstractStatus {

    protected $_data_struct = array(
        'jobs'    => array(),
        'summary' =>
            array(
                "IN_QUEUE_BEFORE"   => 0, "STATUS" => "",
                "TOTAL_SEGMENTS"    => 0, "SEGMENTS_ANALYZED" => 0,
                "TOTAL_FAST_WC"     => 0, "TOTAL_TM_WC" => 0,
                "TOTAL_STANDARD_WC" => 0,
                "STANDARD_WC_TIME"  => 0, "FAST_WC_TIME" => 0, "TM_WC_TIME" => 0,
                "STANDARD_WC_UNIT"  => "", "TM_WC_UNIT" => "", "FAST_WC_UNIT" => "",
                "USAGE_FEE"         => 0.00,
                "PRICE_PER_WORD"    => 0.00, "DISCOUNT" => 0.00
            )
    );
    private $reviseClass;

    public function getResult(){

        switch ( $this->_globals[ 'STATUS_PROJECT' ] ) {
            case 'NEW':
            case 'FAST_OK':
            case 'NOT_READY_FOR_ANALYSIS':
            case 'BUSY':
                $this->result[ 'status' ] = 'ANALYZING';
                break;
            case 'EMPTY':
                $this->result[ 'status' ] = 'NO_SEGMENTS_FOUND';
                break;
            case 'NOT_TO_ANALYZE':
                $this->result[ 'status' ] = 'ANALYSIS_NOT_ENABLED';
                break;
            case 'DONE':
                $this->result[ 'status' ] = 'DONE';
                break;
            default: //this can not be
                $this->result[ 'status' ] = 'FAIL';
                break;
        }

        $this->result['analyze'] = Routes::analyze(array(
            'project_name' => $this->_project_data[0]['pname'],
            'id_project' => $this->_project_data[0]['pid'],
            'password' => $this->_project_data[0]['ppassword']
        ));

        $this->result[ 'jobs' ]    = array();

        foreach ( $this->_project_data as $job ) {

            $this->result[ 'jobs' ][ 'langpairs' ][ $job[ 'jid_jpassword' ] ] = $job[ 'lang_pair' ];
            $this->result[ 'jobs' ][ 'job-url' ][ $job[ 'jid_jpassword' ] ]   = "/translate/" . $job[ 'job_url' ];

        }

        return $this->result;

    }

}