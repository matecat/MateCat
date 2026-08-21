<?php
/**
 * Created by PhpStorm.
 */

namespace Controller\Views\OutsourceTo;

use Controller\Abstracts\BaseKleinViewController;
use Exception;
use LogicException;
use Model\Outsource\ConfirmationStruct;
use TypeError;
use Utils\Logger\LoggerFactory;
use Utils\Shop\Cart;

/**
 * Class AbstractController
 *
 * Manage the generic return controller for a remote Login auth
 * The user will be redirected to this class to get the session quote data.
 *
 */
abstract class AbstractController extends BaseKleinViewController
{

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
     * @throws TypeError
     */
    protected function validateTheRequest(): void
    {
        $this->logger = $this->createLogger();

        // Check if the required properties are set in the concrete class
        if (empty($this->review_order_page)) {
            throw new LogicException("Property 'review_order_page' can not be EMPTY");
        }

        if (empty($this->tokenName)) {
            throw new LogicException("Property 'tokenName' can not be EMPTY");
        }

        if (empty($this->id_vendor)) {
            throw new LogicException("Property 'id_vendor' can not be EMPTY");
        }

        if (empty($this->vendor_name)) {
            throw new LogicException("Property 'vendor_name' can not be EMPTY");
        }

        $filterArgs = [
            $this->tokenName => ['filter' => FILTER_SANITIZE_SPECIAL_CHARS, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH],
            $this->dataKeyName => ['filter' => FILTER_SANITIZE_SPECIAL_CHARS, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH],
        ];

        $__getInput = $this->getInputFiltered($filterArgs);

        $this->tokenAuth = $__getInput[$this->tokenName];

        $this->data_key_content = $__getInput[$this->dataKeyName];

        $this->logger->debug($this->request->paramsGet()->all());
        $this->logger->debug($this->request->server()->get('QUERY_STRING'));
    }

    protected function createLogger(): \Utils\Logger\MatecatLogger
    {
        return LoggerFactory::getLogger('outsource');
    }

    /**
     * @throws TypeError
     */
    protected function createShopCart(): Cart
    {
        return new Cart('outsource_to_external', $this->sessionStore());
    }

    /**
     * @param array<string, array<string, int>> $filterArgs
     * @return array<string,string>
     */
    protected function getInputFiltered(array $filterArgs): array
    {
        return filter_input_array(INPUT_GET, $filterArgs);
    }

    /**
     * @throws LogicException
     * @throws TypeError
     */
    protected function initDependencies(): void
    {
        $this->validateTheRequest();
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function renderView(): void
    {
        $this->shop_cart = $this->createShopCart();

        if (!$this->shop_cart->countItems()) {
            // redirectFailurePage is a white page with an error for session expired
            $this->setView("redirectFailurePage.html", [], 500);
        } else {
            // redirectSuccessPage is a white page with a form submitted by javascript
            $this->setView("redirectSuccessPage.html", $this->buildTemplateVars());
        }

        $this->render();
    }

    /**
     * Build the template vars for the redirect success page.
     *
     * @return array<string, mixed>
     * @throws Exception
     * @throws LogicException
     * @throws TypeError
     */
    private function buildTemplateVars(): array
    {
        $item = $this->shop_cart->getItem($this->data_key_content);
        if ($item === null) {
            throw new LogicException('Cart item not found for key: ' . $this->data_key_content);
        }

        return [
            'tokenAuth'      => $this->tokenAuth,
            'data'           => json_encode([$item]),
            'redirect_url'   => $this->review_order_page,
            'data_key'     => $this->data_key_content,
        ];
    }

}
