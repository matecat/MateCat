<?php


abstract class OutsourceTo_AbstractSuccessController extends viewController {

    /**
     * This url is the page where the user will be redirected after he performed the login on the Provider
     * website
     *
     * The user will be redirected there to get the session quote data.
     *
     * It can be NULL if the review/confirm procedure already occurred on the external Service website
     *
     * because of this controller will not used, else it MUST be filled
     *
     * @var string
     */
    protected $review_order_page = '';

    protected $tokenAuth;

	public function __construct() {

        if( empty( $this->review_order_page ) ){
            throw new LogicException( "Property 'review_order_page' can not be EMPTY" );
        }

        //SESSION ENABLED

		parent::__construct(false);
		parent::makeTemplate("redirectSuccessPage.html");

        $filterArgs = array(
                'tk' => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
        );

        $__getInput = filter_input_array( INPUT_GET, $filterArgs );

        /*
         *
         * Do something with Token ( send it for authentication on confirm )
         *
         *  $__getInput['tk']
         *
         */
        $this->tokenAuth = $__getInput['tk'];

	}

	public function doAction() {}


	public function setTemplateVars() {

        $shop_cart = Shop_Cart::getInstance('outsource_to_external');

        //we need a list not an hashmap
        $item_list = array();
        foreach( $shop_cart->getCart() as $item ){
            $item_list[ ] = $item;
        }

        $this->template->tokenAuth = $this->tokenAuth;
        $this->template->data = json_encode( $item_list );
        $this->template->redirect_url = $this->review_order_page;

    }

}
