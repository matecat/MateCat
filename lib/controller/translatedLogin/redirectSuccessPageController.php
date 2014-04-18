<?php


class translatedLogin_redirectSuccessPageController extends viewController {



	public function __construct() {
		parent::__construct(false);
		parent::makeTemplate("redirectSuccessPage.html");
	}

	public function doAction() {}


	public function setTemplateVars() {

        $shop_cart = Shop_Cart::getInstance('outsource_to_translated');

        //we need a list not an hashmap
        $item_list = array();
        foreach( $shop_cart->getCart() as $item ){
            $item_list[ ] = $item;
        }

        $this->template->data = json_encode( $item_list );
        $this->template->redirect_url = 'https://www.translated.home/mcl/review.php';

    }

}
