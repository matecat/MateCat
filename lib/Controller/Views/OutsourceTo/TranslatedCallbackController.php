<?php
/**
 * Created by PhpStorm.
 */

namespace Controller\Views\OutsourceTo;

use Model\Outsource\TranslatedConfirmationStruct;

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
class TranslatedCallbackController extends AbstractController
{

    /**
     * Redirect page to review the order
     *
     * @see AbstractController::$review_order_page
     * @var string
     */
    protected string $review_order_page = 'https://signin.translated.net/review.php';

    /**
     * Token key name for the authentication return
     *
     * @see AbstractController::$tokenName
     * @var string
     */
    protected string $tokenName = 'tk';

    /**
     * Key that holds extra info
     *
     * @see extraInfoName::$extra_info
     * @var string
     */
    protected string $dataKeyName = 'data_key';

    /**
     * @var int|null
     */
    protected ?int $id_vendor = TranslatedConfirmationStruct::VENDOR_ID;

    /**
     * @var string|null
     */
    protected ?string $vendor_name = TranslatedConfirmationStruct::VENDOR_NAME;

}
