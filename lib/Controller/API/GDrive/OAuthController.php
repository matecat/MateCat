<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/11/2016
 * Time: 16:05
 */

namespace Controller\API\GDrive;

use Controller\Abstracts\AbstractStatefulKleinController;
use Google\Service\Exception;
use Model\ConnectedServices\GDrive\GDriveUserAuthorizationModel;
use Model\Exceptions\ValidationError;
use ReflectionException;
use Utils\Registry\AppConfig;

class OAuthController extends AbstractStatefulKleinController
{

    /**
     * @throws ReflectionException
     * @throws ValidationError
     * @throws Exception
     */
    public function response(): void
    {
        if (empty($this->request->param('state')) || $_SESSION[ 'googledrive-' . AppConfig::$XSRF_TOKEN ] !== $this->request->param('state')) {
            $this->response->code(401);

            return;
        }

        unset($_SESSION[ 'google-drive-' . AppConfig::$XSRF_TOKEN ]);

        $code  = $this->request->param('code');
        $error = $this->request->param('error');

        if (isset($code) && $code) {
            $this->__handleCode($code);
        } elseif (isset($error)) {
            $this->__handleError($error);
        }

        $body = <<<EOF
<html><head>
<script> window.close(); </script>
</head>
</html>
EOF;

        $this->response->body($body);
    }

    private function __handleError($error)
    {
    }

    /**
     * @throws ValidationError
     * @throws ReflectionException
     * @throws Exception
     */
    private function __handleCode(string $code): void
    {
        $model = new GDriveUserAuthorizationModel($this->user);
        $model->updateOrCreateRecordByCode($code);
        $this->refreshClientSessionIfNotApi();
    }

}