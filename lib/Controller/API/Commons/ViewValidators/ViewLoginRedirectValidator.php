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
use Utils\Registry\AppConfig;

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
            $_SESSION['wanted_url'] = ltrim($_SERVER['REQUEST_URI'], '/');
            header("Location: " . AppConfig::$HTTPHOST . AppConfig::$BASEURL . "signin", false);
            exit;
        } elseif (isset($_SESSION['wanted_url'])) {
            // handle redirect after login
            /** @var $controller BaseKleinViewController */
            $controller = $this->controller;
            $controller->redirectToWantedUrl();
        }
    }
}