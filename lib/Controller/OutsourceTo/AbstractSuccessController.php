<?php
/**
 * Created by PhpStorm.
 */

/**
 * Class OutsourceTo_AbstractSuccessController
 *
 * Manage the generic return controller for a remote Login auth
 * The user will be redirected on this class to get the session quote data.
 *
 */
abstract class OutsourceTo_AbstractSuccessController extends viewController {

    /**
     *
     * This url is the page where the user will be redirected after he performed the login on the Provider
     * website
     *
     * It can be NULL if the review/confirm procedure already occurred on the external Service website
     *
     * because of this controller will not used, else it MUST be filled
     *
     * @var string
     */
    protected $review_order_page = '';

    /**
     * The name of the key that transport the authentication token.
     *
     * MUST BE SET IN Concrete Class
     *
     * @var string
     */
    protected $tokenName;

    /**
     * The token authentication from remote service Login
     *
     * @var string
     */
    protected $tokenAuth;

    /**
     * Key that holds extra info
     *
     * @var mixed|string
     */
    protected $dataKeyName;

    /**
     * Extra info as the project id
     *
     * @var mixed|string
     */
    protected $data_key_content;

    /**
     * Class Constructor
     *
     * @throws LogicException
     *
     */
    public function __construct() {

        if( empty( $this->review_order_page ) ){
            throw new LogicException( "Property 'review_order_page' can not be EMPTY" );
        }

        if( empty( $this->tokenName ) ){
            throw new LogicException( "Property 'tokenName' can not be EMPTY" );
        }

        //SESSION ENABLED
        $this->sessionStart();
        parent::__construct(false);


        $filterArgs = array(
                $this->tokenName  => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
                $this->dataKeyName => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
        );

        $__getInput = filter_input_array( INPUT_GET, $filterArgs );

        /*
         *
         * Do something with Token ( send it for authentication on confirm )
         *
         *  $__getInput['tk']
         *
         */
        $this->tokenAuth = $__getInput[ $this->tokenName ];

        $this->data_key_content = $__getInput[ $this->dataKeyName ];

        Log::doLog( $_GET );
        Log::doLog( $_SERVER['QUERY_STRING'] );

	}

    /**
     * Empty at moment
     *
     * @return mixed|void
     */
    public function doAction() {}

    /**
     * Set the template vars to the redirect Page
     *
     * @return mixed|void
     */
    public function setTemplateVars() {

        $shop_cart = Shop_Cart::getInstance('outsource_to_external');

        if ( !$shop_cart->countItems() ){
            /**
             * redirectFailurePage is a white page with an error for session expired
             *
             */
            parent::makeTemplate("redirectFailurePage.html");

            return null;

        } else {
            /**
             * redirectSuccessPage is a white page with a form submitted by javascript
             *
             */
            parent::makeTemplate("redirectSuccessPage.html");
        }

        //we need a list not an hashmap
        $item_list = array();
        foreach( array( $shop_cart->getItem( $this->data_key_content ) ) as $item ){
            $item_list[ ] = $item;
        }

        $this->template->tokenAuth = $this->tokenAuth;
        $this->template->data = json_encode( $item_list );
        $this->template->redirect_url = $this->review_order_page;
        $this->template->data_key = $this->data_key_content;

        //clear the cart after redirection
        //$shop_cart->emptyCart();

    }

}
