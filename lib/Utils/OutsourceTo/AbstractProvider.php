<?php
/**
 * Created by PhpStorm.
 */

/**
 * Abstract class of a Provider to extend to implement a login/review/confirm communication
 *
 * @author domenico domenico@translated.net / ostico@gmail.com
 *
 * Date: 29/04/14
 * Time: 10.54
 *
 */
abstract class OutsourceTo_AbstractProvider {

    /**
     * These are the url where the user will be redirected after
     * he performed the login on the external service
     *
     * Set them appropriately in the constructor.
     *
     * They can be null if an all in one login/review/confirm is implemented on the external provider system
     *
     * @var string
     */
    protected $_outsource_login_url_ok = "";

    /**
     * These are the url where the user will be redirected after
     * has failed the login on the external service
     *
     * Set them appropriately in the constructor.
     *
     * They can be null if an all in one login/review/confirm is implemented on the external provider system
     *
     * @var string
     */
    protected $_outsource_login_url_ko = "";

    /**
     * These are the url that the vendor system must call in order to confirm the outsource to MateCat
     *
     * Set them appropriately in the constructor.
     *
     * @var string
     */
    protected $_outsource_url_confirm = "";


    /**
     * Class constructor
     *
     * Here will be defined the callback urls for success or failure on login system
     *
     * @see OutsourceTo_AbstractProvider::$_outsource_login_url_ok
     *
     * @see OutsourceTo_AbstractProvider::$_outsource_login_url_ko
     *
     */
    public function __construct() {
    }

    /**
     * Object containing the quote result
     *
     * @var Shop_AbstractItem[]
     */
    protected $_quote_result;

    /**
     * @var int Project ID
     */
    protected $pid = 0;

    /**
     * @var string Project Password
     */
    protected $ppassword = '';

    /**
     * @var string currency
     */
    protected $currency = "EUR";

    /**
     * @var string timezone
     */
    protected $timezone = "0";

    /**
     * List of job Ids and relative passwords that will be sent to the provider for quoting
     *
     * <pre>
     * Ex:
     *   array(
     *      0 => array(
     *          'id' => 5901,
     *          'jpassword' => '6decb661a182',
     *      ),
     *   );
     * </pre>
     *
     * @var array List of job ids and relative passwords
     */
    protected $jobList = [];

    /**
     * Perform Quotes to the selected Provider
     *
     * @param array|null $volAnalysis
     *
     * @return void
     */
    abstract public function performQuote( $volAnalysis = null );

    /**
     * Get quotes Result after Provider Interrogation
     *
     * @return Shop_AbstractItem[]
     */
    public function getQuotesResult() {
        return $this->_quote_result;
    }

    /**
     * Set The Job List
     *
     * <pre>
     * Ex:
     *   array(
     *      0 => array(
     *          'id' => 5901,
     *          'jpassword' => '6decb661a182',
     *      ),
     *   );
     * </pre>
     *
     * @param array $jobList
     *
     * @return $this
     */
    public function setJobList( $jobList ) {
        $this->jobList = $jobList;

        return $this;
    }

    /**
     * Set the right project ID for the outsource request
     *
     * @param int $pid
     *
     * @return $this
     */
    public function setPid( $pid ) {
        $this->pid = $pid;

        return $this;
    }

    /**
     * Set the Password for the project
     *
     * @param string $ppassword
     *
     * @return $this
     */
    public function setPpassword( $ppassword ) {
        $this->ppassword = $ppassword;

        return $this;
    }

    /**
     * Set the currency for the project
     *
     * @param string $currency
     *
     * @return $this
     */
    public function setCurrency( $currency ) {
        if ( !empty( $currency ) ) {
            $this->currency = $currency;
        }

        return $this;
    }

    /**
     * Set the timezone for the project
     *
     * @param string $timezone
     *
     * @return $this
     */
    public function setTimezone( $timezone ) {
        if ( !empty( $timezone ) || $timezone === "0" ) {
            $this->timezone = $timezone;
        }

        return $this;
    }

    /**
     * Get the url for return callback on failed login
     *
     * @return string
     */
    public function getOutsourceLoginUrlKo() {
        return $this->_outsource_login_url_ko;
    }

    /**
     * Get the url for return callback on success login
     *
     * @return string
     */
    public function getOutsourceLoginUrlOk() {
        return $this->_outsource_login_url_ok;
    }

    public function getOutsourceConfirm() {
        return $this->_outsource_url_confirm;
    }

}