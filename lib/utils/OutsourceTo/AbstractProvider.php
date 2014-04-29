<?php
/**
 * Created by PhpStorm.
 * User: domenico domenico@translated.net / ostico@gmail.com
 * Date: 29/04/14
 * Time: 10.54
 * 
 */

abstract class OutsourceTo_AbstractProvider {

    /**
     * These are the urls where the user will be redirected after
     * he performed the login on the external service
     *
     * Set them appropriately in the constructor.
     *
     * They can be null if an all in one login/review/confirm is implemented on the external provider system
     *
     * @var string
     */
    protected $_outsource_login_url_ok = "";
    protected $_outsource_login_url_ko = "";


    /**
     *
     * @var Shop_AbstractItem[]
     */
    protected $_quote_result;

    /**
     * @var int project ID
     */
    protected $pid = 0;

    /**
     * @var string Project Password
     */
    protected $ppassword = '';

    /**
     * Ex:
     *   array(
     *      0 => array(
     *              'id' => 5901,
     *              'jpassword' => '6decb661a182',
     *           ),
     *   );
     *
     * @var array List of job ids and relative passwords
     */
    protected $jobList = array();

    /**
     * Perform Quotes to the selected Provider
     *
     * @param null $volAnalysis
     *
     * @return void
     */
    abstract public function performQuote( $volAnalysis = null );

    /**
     * Get quotes Result after Provider Interrogation
     *
     * @return Shop_AbstractItem[]
     */
    public function getQuotesResult(){
        return $this->_quote_result;
    }

    /**
     * @param array $jobList
     *
     * @return $this
     */
    public function setJobList( $jobList ) {
        $this->jobList = $jobList;

        return $this;
    }

    /**
     * @param int $pid
     *
     * @return $this
     */
    public function setPid( $pid ) {
        $this->pid = $pid;

        return $this;
    }

    /**
     * @param string $ppassword
     *
     * @return $this
     */
    public function setPpassword( $ppassword ) {
        $this->ppassword = $ppassword;

        return $this;
    }

    /**
     * @return string
     */
    public function getOutsourceLoginUrlKo() {
        return $this->_outsource_login_url_ko;
    }

    /**
     * @return string
     */
    public function getOutsourceLoginUrlOk() {
        return $this->_outsource_login_url_ok;
    }

}