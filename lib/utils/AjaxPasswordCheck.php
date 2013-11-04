<?php
/**
 * User: domenico
 * Date: 07/08/13
 * Time: 15.31
 */

/**
 * Job Password Check Tool for ajax requests
 *
 */
class AjaxPasswordCheck {

    /**
     * jobData structure reference
     *
     * @var array
     */
    protected $jobData = array(
            'source'        => null,
            'target'        => null,
            'id_mt_engine'  => null,
            'id_tms'        => null,
            'id_translator' => null,
            'status'        => null,
            'password'      => null
    );

    public function getData(){
        return $this->jobData;
    }

    /**
     * Check password with jobData array
     * <pre>
     * jobData = array(
     *      'source'                => 'it-IT',
     *      'target'                => 'en-US',
     *      'id_mt_engine'          => 1,
     *      'id_tms'                => 1,
     *      'id_translator'         => '',
     *      'status'                => 'active',
     *      'password'              => 'GfgJ6h'
     *      'job_first_segment'     => '2138134'
     *      'job_last_segment'      => '2140000'
     * );
     * </pre>
     *
     * @param array    $jobData
     * @param string   $password
     * @param null|int $segmentID
     *
     * @return bool
     */
    public function grantJobAccessByJobData( array $jobData, $password, $segmentID = null ){
        $this->jobData = $jobData;
        if( isset( $this->jobData[0] ) && is_array( $this->jobData[0] ) ){
            $result = array();
            foreach( $this->jobData as $jD ){
                $result[] = ( $this->_grantJobAccess( $jD['password'], $password ) && $this->_grantSegmentPermission( $jD, $segmentID ) );
            }
            if ( array_search( true, $result, true ) !== false ) return true;

        } else {
            return ( $this->_grantJobAccess( $this->jobData['password'], $password ) && $this->_grantSegmentPermission( $this->jobData, $segmentID ) );
        }

        return false;
    }

    /**
     * Simple string comparison and external password filtering
     *
     * @param $dbPass string
     * @param $password string
     *
     * @return bool
     */
    protected function _grantJobAccess( $dbPass, $password ){
        $password = filter_var( $password, FILTER_SANITIZE_STRING, array( 'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW ) );
        if( $dbPass === $password ){
            return true;
        }
        return false;
    }

    protected function _grantSegmentPermission( $jobData, $segmentID ){
        //if segmentID is null no request of check was made
        if( is_null( $segmentID ) || ( $segmentID >= $jobData['job_first_segment'] && $segmentID <= $jobData['job_last_segment'] ) ){
            return true;
        }
        return false;
    }

}