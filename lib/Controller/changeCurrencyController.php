<?php
/**
 * Controller which calls an external service to compute a quote resulting price with different currencies
 *
 * WORKFLOW:
 *  Receive a POST ajax call containing: startingCurrency, targetCurrency and amount
 *  Call an external service (via cURL) to retrieve the equivalent amount in the targetCurrency
 *  return the new amount as result of the ajax call
 */

/**
 * Class changeCurrencyController
 */
class changeCurrencyController extends ajaxController {

    /**
     * The starting currency
     * @var float
     */
    private $amount;

    /**
     * The starting currency
     * @var string
     */
    private $currencyFrom;

    /**
     * The starting currency
     * @var string
     */
    private $currencyTo;

    /**
     * Class constructor, validate/sanitize incoming params
     *
     */
    public function __construct() {

        parent::__construct();

        /* Retrieve parameetrs from post, and sanitize their values */
        $filterArgs = array(
                'amount'          => array( 'filter' => FILTER_SANITIZE_NUMBER_FLOAT, 'flags' => FILTER_FLAG_ALLOW_FRACTION ),
                'currencyFrom'    => array( 'filter' => FILTER_SANITIZE_STRING ),
                'currencyTo'      => array( 'filter' => FILTER_SANITIZE_STRING )
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->amount       = $__postInput[ 'amount' ];
        $this->currencyFrom = $__postInput[ 'currencyFrom' ];
        $this->currencyTo   = $__postInput[ 'currencyTo' ];


        /* Check the parameters consistency and correctness */
        if( empty( $this->amount ) ){
            $this->result[ 'errors' ][] = array( "code" => -1, "message" => "No amount provided" );
        }

        if( empty( $this->currencyFrom ) ){
            $this->result[ 'errors' ][] = array( "code" => -2, "message" => "No starting currency provided" );
        }

        if( empty( $this->currencyTo ) ){
            $this->result[ 'errors' ][] = array( "code" => -3, "message" => "No target currency provided" );
        }
    }

    /**
     * Perform Controller Action
     *
     * @return float|null
     */
    public function doAction() {

        if( !empty( $this->result[ 'errors' ] ) ){
            return -1; // ERROR
        }

        $conversionService      = new currency_translatedCurrencyConverter();
        $conversionService->setAmount( $this->amount );
        $conversionService->setCurrencyFrom( $this->currencyFrom );
        $conversionService->setCurrencyTo( $this->currencyTo );

        $conversionService->computeNewAmount();

        $this->result[ 'code' ] = 1;
        $this->result[ 'data' ] = $conversionService->getNewAmount();
    }

}
