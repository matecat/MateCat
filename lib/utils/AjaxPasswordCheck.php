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
     *      'source'        => 'it-IT',
     *      'target'        => 'en-US',
     *      'id_mt_engine'  => 1,
     *      'id_tms'        => 1,
     *      'id_translator' => '',
     *      'status'        => 'active',
     *      'password'      => 'GfgJ6h'
     * );
     * </pre>
     *
     * @param array $jobData
     * @param       $password
     *
     * @return bool
     */
    public function grantJobAccessByJobData( array $jobData, $password ){
        $this->jobData = $jobData;
        if( isset( $this->jobData[0] ) && is_array( $this->jobData[0] ) ){
            foreach( $this->jobData as $jD ){
                $res = $this->_grantJobAccess( $jD['password'], $password );
                if( $res == true ) return $res;
            }
        } else {
            return $this->_grantJobAccess( $this->jobData['password'], $password );
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

}