<?php
/**
 * Created by PhpStorm.
 */

use Outsource\TranslatedConfirmationStruct;

/**
 * Controller that handle the success return from login page
 * The user will be redirected on this class to get the session quote data.
 *
 * Used to set the next redirect page on remote provider system
 *
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 29/04/14
 * Time: 12.23
 * 
 */
class OutsourceTo_TranslatedSuccessController extends OutsourceTo_AbstractSuccessController {

    /**
     * Redirect page to review the order
     *
     * @see OutsourceTo_AbstractSuccessController::$review_order_page
     * @var string
     */
    protected $review_order_page = 'https://signin.translated.net/review.php';

    /**
     * Token key name for the authentication return
     *
     * @see OutsourceTo_AbstractSuccessController::$tokenName
     * @var string
     */
    protected $tokenName = 'tk';

    /**
     * Key that holds extra info
     *
     * @see extraInfoName::$extra_info
     * @var string
     */
    protected $dataKeyName = 'data_key';

    /**
     * @var int
     */
    protected $id_vendor = TranslatedConfirmationStruct::VENDOR_ID;

    /**
     * @var string
     */
    protected $vendor_name = TranslatedConfirmationStruct::VENDOR_NAME;

}
