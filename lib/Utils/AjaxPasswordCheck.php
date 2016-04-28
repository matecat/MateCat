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
            'source'            => null,
            'target'            => null,
            'id_mt_engine'      => null,
            'id_tms'            => null,
            'id_translator'     => null,
            'status'            => null,
            'password'          => null,
            'job_first_segment' => null,
            'job_last_segment'  => null
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
     * @param array|Jobs_JobStruct    $jobData
     * @param string   $password
     * @param null|int $segmentID
     *
     * @return bool
     */
    public function grantJobAccessByJobData( $jobData, $password, $segmentID = null ){
        $this->jobData = $jobData;

        //array of jobs permitted because of job split
        if( isset( $this->jobData[0] ) &&
                ( is_array( $this->jobData[0] )
                        || $this->jobData[0] instanceof Jobs_JobStruct
                        || $this->jobData[0] instanceof Chunks_ChunkStruct )
        ){

            //we have to find at least one job with the correct password inside the job array
            $result = array();
            foreach( $this->jobData as $jD ){
                $result[] = ( $this->_grantAccess( $jD['password'], $password ) && $this->_grantSegmentPermission( $jD, $segmentID ) );
            }

            //One must be true, else deny access
            if ( array_search( true, $result, true ) !== false ) return true;

        } else {

            //simple job request
            return ( $this->_grantAccess( $this->jobData['password'], $password ) && $this->_grantSegmentPermission( $this->jobData, $segmentID ) );
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
    protected function _grantAccess( $dbPass, $password ){
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

    public function grantProjectAccess( array $projectJobData, $ppassword = null, $jpassword = null ){

        if( empty($ppassword) && empty($jpassword) ){
            return false;
        }

        $result = array();
        foreach( $projectJobData as $pJD ){

            //if password is null no request of job check was made
            $_check_jproject = ( is_null( $ppassword ) ? true : $this->_grantAccess( $pJD['ppassword'], $ppassword ) );
            $_check_job      = ( is_null( $jpassword ) ? true : $this->_grantAccess( $pJD['jpassword'], $jpassword ) );

            $result[] = $_check_jproject && $_check_job ;
        }

        if ( array_search( true, $result, true ) !== false ) return true;
        return false;

    }

    public function grantProjectJobAccessOnJobPass( array $projectJobData, $ppassword, $jpassword ){
        return $this->grantProjectAccess( $projectJobData, $ppassword, $jpassword );
    }


    public function grantProjectAccessOnJobID( array $projectJobData, $ppassword, $jobID ){

        $job_list = array();
        foreach( $projectJobData as $pJD ){
            $job_list[] = $pJD['jid'];
        }

        //no job id provided, deny access
        if( array_search( $jobID, $job_list, true ) === false ) return false;

        return $this->grantProjectAccess( $projectJobData, $ppassword );

    }

}