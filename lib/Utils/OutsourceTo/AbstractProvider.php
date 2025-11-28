<?php
/**
 * Created by PhpStorm.
 */

namespace Utils\OutsourceTo;

use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use Utils\Shop\AbstractItem;

/**
 * Abstract class of a Provider to extend to implement a login/review/confirm communication
 *
 * @author domenico domenico@translated.net / ostico@gmail.com
 *
 * Date: 29/04/14
 * Time: 10.54
 *
 */
abstract class AbstractProvider
{

    /**
     * These are the url where the user will be redirected after
     * he performed the login on the external service
     *
     * Set them appropriately in the constructor.
     *
     * They can be null if an all-in-one login/review/confirm is implemented on the external provider system
     *
     * @var string
     */
    protected string $_outsource_login_url_ok = "";

    /**
     * These are the url where the user will be redirected after
     * has failed the login on the external service
     *
     * Set them appropriately in the constructor.
     *
     * They can be null if an all-in-one login/review/confirm is implemented on the external provider system
     *
     * @var string
     */
    protected string $_outsource_login_url_ko = "";

    /**
     * These are the url that the vendor system must call to confirm the outsourcing to Matecat
     *
     * Set them appropriately in the constructor.
     *
     * @var string[]
     */
    protected array $_outsource_url_confirm = [];


    /**
     * Class constructor
     *
     * Here will be defined the callback urls for success or failure on a login system
     *
     * @see AbstractProvider::$_outsource_login_url_ok
     *
     * @see AbstractProvider::$_outsource_login_url_ko
     *
     */
    public function __construct()
    {
    }

    /**
     * Object containing the quote result
     *
     * @var AbstractItem[]
     */
    protected array $_quote_result;

    /**
     * @var int Project ID
     */
    protected int $pid = 0;

    /**
     * @var string Project Password
     */
    protected string $ppassword = '';

    /**
     * @var string currency
     */
    protected string $currency = "EUR";

    /**
     * @var string timezone
     */
    protected string $timezone = "0";

    protected ?FeatureSet $features = null;

    protected ?UserStruct $user = null;

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
    protected array $jobList = [];

    /**
     * Perform Quotes to the selected Provider
     *
     * @return void
     */
    abstract public function performQuote(): void;

    /**
     * Get quotes Result after Provider Interrogation
     *
     * @return AbstractItem[]
     */
    public function getQuotesResult(): array
    {
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
    public function setJobList(array $jobList): AbstractProvider
    {
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
    public function setPid(int $pid): AbstractProvider
    {
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
    public function setPpassword(string $ppassword): AbstractProvider
    {
        $this->ppassword = $ppassword;

        return $this;
    }

    /**
     * @param FeatureSet|null $features
     *
     * @return $this
     */
    public function setFeatures(?FeatureSet $features): AbstractProvider
    {
        if (!empty($features)) {
            $this->features = $features;
        }

        return $this;
    }

    /**
     * @param UserStruct|null $user
     *
     * @return $this
     */
    public function setUser(?UserStruct $user): AbstractProvider
    {
        if (!empty($user)) {
            $this->user = $user;
        }

        return $this;
    }

    /**
     * Set the currency for the project
     *
     * @param string $currency
     *
     * @return $this
     */
    public function setCurrency(string $currency): AbstractProvider
    {
        if (!empty($currency)) {
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
    public function setTimezone(string $timezone): AbstractProvider
    {
        if (!empty($timezone) || $timezone === "0") {
            $this->timezone = $timezone;
        }

        return $this;
    }

    /**
     * Get the url for the return callback on failed login
     *
     * @return string
     */
    public function getOutsourceLoginUrlKo(): string
    {
        return $this->_outsource_login_url_ko;
    }

    /**
     * Get the url for the return callback on success login
     *
     * @return string
     */
    public function getOutsourceLoginUrlOk(): string
    {
        return $this->_outsource_login_url_ok;
    }

    public function getOutsourceConfirmUrl(): array
    {
        return $this->_outsource_url_confirm;
    }

}