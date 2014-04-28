<?php


class translatedLogin_redirectSuccessPageController extends viewController {

    protected $tokenAuth;

	public function __construct() {

        //SESSION ENABLED

		parent::__construct(false);
		parent::makeTemplate("redirectSuccessPage.html");

        $filterArgs = array(
                'tk' => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
        );

        $__getInput = filter_input_array( INPUT_GET, $filterArgs );

        /*
         *
         * Do something with Token ( send it for authentication on confirm ?! )
         *
         *  $__getInput['tk']
         *
         */
        $this->tokenAuth = $__getInput['tk'];

	}

	public function doAction() {}


	public function setTemplateVars() {

        $shop_cart = Shop_Cart::getInstance('outsource_to_translated');

        //we need a list not an hashmap
        $item_list = array();
        foreach( $shop_cart->getCart() as $item ){
            $item_list[ ] = $item;
        }

        $this->template->tokenAuth = $this->tokenAuth;
        $this->template->data = json_encode( $item_list );
        $this->template->redirect_url = 'http://signin.translated.net/review.php';
//        $this->template->redirect_url = 'http://openid.loc/review.php';

    }

}
