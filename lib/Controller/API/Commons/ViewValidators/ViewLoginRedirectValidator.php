<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/04/17
 * Time: 21.19
 *
 */

namespace Controller\API\Commons\ViewValidators;


use Controller\Abstracts\BaseKleinViewController;
use Controller\API\Commons\Validators\Base;

class ViewLoginRedirectValidator extends Base
{

    /**
     * @param BaseKleinViewController $controller
     */
    public function __construct(BaseKleinViewController $controller)
    {
        parent::__construct($controller);
    }

    public function _validate(): void
    {
        if (!$this->controller->isLoggedIn()) {
            /** @var BaseKleinViewController $controller */
            $controller = $this->controller;
            $controller->redirectToSignin();
        } elseif (isset($_SESSION['wanted_url'])) {
            // handle redirect after login
            /** @var BaseKleinViewController $controller */
            $controller = $this->controller;
            $controller->redirectToWantedUrl();
        }
    }
}