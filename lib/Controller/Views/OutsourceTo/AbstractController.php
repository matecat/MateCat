<?php
/**
 * Created by PhpStorm.
 */

namespace Controller\Views\OutsourceTo;

use Controller\Abstracts\BaseKleinViewController;
use Exception;
use LogicException;
use Model\Outsource\ConfirmationStruct;
use Utils\Logger\LoggerFactory;
use Utils\Shop\Cart;
use Utils\Tools\SimpleJWT;

/**
 * Class AbstractController
 *
 * Manage the generic return controller for a remote Login auth
 * The user will be redirected to this class to get the session quote data.
 *
 */
abstract class AbstractController extends BaseKleinViewController {

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
    protected string $review_order_page = '';

    /**
     * The name of the key that transports the authentication token.
     *
     * MUST BE SET IN Concrete Class
     *
     * @var string
     */
    protected string $tokenName = '';

    /**
     * The token authentication from remote service Login
     *
     * @var string
     */
    protected string $tokenAuth;

    /**
     * Key that holds extra info
     *
     * @var string
     */
    protected string $dataKeyName = '';

    /**
     * Extra info as the project id
     *
     * @var mixed|string
     */
    protected $data_key_content;

    /**
     * @var \Utils\Shop\Cart
     */
    protected Cart $shop_cart;

    /**
     * @var int|null
     */
    protected ?int $id_vendor = ConfirmationStruct::VENDOR_ID;

    /**
     * @var string|null
     */
    protected ?string $vendor_name = ConfirmationStruct::VENDOR_NAME;

    /**
     * @return void
     * @throws LogicException
     */
    protected function validateTheRequest() {

        $this->logger = LoggerFactory::getLogger( 'outsource' );

        // Check if the required properties are set in the concrete class
        if ( empty( $this->review_order_page ) ) {
            throw new LogicException( "Property 'review_order_page' can not be EMPTY" );
        }

        if ( empty( $this->tokenName ) ) {
            throw new LogicException( "Property 'tokenName' can not be EMPTY" );
        }

        if ( empty( $this->id_vendor ) ) {
            throw new LogicException( "Property 'id_vendor' can not be EMPTY" );
        }

        if ( empty( $this->vendor_name ) ) {
            throw new LogicException( "Property 'vendor_name' can not be EMPTY" );
        }

        $filterArgs = [
                $this->tokenName   => [ 'filter' => FILTER_SANITIZE_SPECIAL_CHARS, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ],
                $this->dataKeyName => [ 'filter' => FILTER_SANITIZE_SPECIAL_CHARS, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ],
        ];

        $__getInput = filter_input_array( INPUT_GET, $filterArgs );

        $this->tokenAuth = $__getInput[ $this->tokenName ];

        $this->data_key_content = $__getInput[ $this->dataKeyName ];

        $this->logger->debug( $_GET );
        $this->logger->debug( $_SERVER[ 'QUERY_STRING' ] );

    }

    protected function afterConstruct() {
        $this->validateTheRequest();
    }

    /**
     * @throws Exception
     */
    public function renderView() {

        $this->shop_cart = Cart::getInstance( 'outsource_to_external' );

        if ( !$this->shop_cart->countItems() ) {
            /**
             * redirectFailurePage is a white page with an error for session expired
             *
             */
            $this->setView( "redirectFailurePage.html", [], 500 );
        } else {
            /**
             * redirectSuccessPage is a white page with a form submitted by javascript
             *
             */
            $this->setView( "redirectSuccessPage.html" );
        }

        $this->setTemplateVars();
        $this->render();

    }

    /**
     * Set the template vars to the redirect Page
     *
     * @return void
     * @throws Exception
     */
    public function setTemplateVars() {

        //we need a list, not a hashmap
        $item_list      = [];
        $confirm_tokens = [];

        $item           = $this->shop_cart->getItem( $this->data_key_content );
        $item_list[]    = $item;

        [ $id_job, $password, ] = explode( "-", $item[ 'id' ] );

        $payload                    = [];
        $payload[ 'id_vendor' ]     = $this->id_vendor;
        $payload[ 'vendor_name' ]   = $this->vendor_name;
        $payload[ 'id_job' ]        = (int)$id_job;
        $payload[ 'password' ]      = $password;
        $payload[ 'currency' ]      = $item[ 'currency' ];
        $payload[ 'price' ]         = round( $item[ 'price' ], PHP_ROUND_HALF_UP );
        $payload[ 'delivery_date' ] = $item[ 'delivery' ];
        $payload[ 'quote_pid' ]     = $item[ 'quote_pid' ];

        $JWT = new SimpleJWT( $payload );
        $JWT->setTimeToLive( 60 * 20 ); //20 minutes to complete the order

        $confirm_tokens[ $item[ 'id' ] ] = $JWT->jsonSerialize();

        $this->addParamsToView( [
                'tokenAuth'      => $this->tokenAuth,
                'data'           => json_encode( $item_list ),
                'redirect_url'   => $this->review_order_page,
                'data_key'       => $this->data_key_content,
                'confirm_tokens' => $confirm_tokens,
        ] );

    }

}
