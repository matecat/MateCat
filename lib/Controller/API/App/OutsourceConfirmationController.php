<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 13/03/17
 * Time: 19.48
 *
 */

namespace Controller\API\App;


use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Exception;
use Model\Jobs\JobDao;
use Model\Outsource\ConfirmationDao;
use Model\Outsource\TranslatedConfirmationStruct;
use Model\Translators\TranslatorsModel;
use ReflectionException;
use Utils\Tools\SimpleJWT;

class OutsourceConfirmationController extends AbstractStatefulKleinController
{

    /**
     * @throws ReflectionException
     * @throws AuthorizationError
     * @throws Exception
     */
    public function confirm(): void
    {
        $params = filter_var_array($this->request->params(), [
                'id_job'   => FILTER_SANITIZE_SPECIAL_CHARS,
                'password' => FILTER_SANITIZE_SPECIAL_CHARS,
                'payload'  => FILTER_SANITIZE_SPECIAL_CHARS,
        ]);

        $payload = SimpleJWT::getValidPayload($params[ 'payload' ]);

        if ($params[ 'id_job' ] != $payload[ 'id_job' ] || $params[ 'password' ] != $payload[ 'password' ]) {
            throw new AuthorizationError("Invalid Job");
        }

        $jStruct           = (JobDao::getByIdAndPassword($params[ 'id_job' ], $params[ 'password' ]));
        $translatorModel   = new TranslatorsModel($jStruct, 0);
        $jTranslatorStruct = $translatorModel->getTranslator();

        $confirmationStruct = new TranslatedConfirmationStruct($payload);

        if (!empty($jTranslatorStruct)) {
            $translatorModel->changeJobPassword();
            $confirmationStruct->password = $jStruct->password;
        }

        $confirmationStruct->create_date = date(DATE_ATOM, time());
        $cDao                            = new ConfirmationDao();
        $cDao->insertStruct($confirmationStruct, ['ignore' => true, 'no_nulls' => true]);
        $cDao->destroyConfirmationCache($jStruct);

        $confirmationArray = $confirmationStruct->toArray();
        unset($confirmationArray[ 'id' ]);

        $this->response->json([
                'errors'  => [],
                'confirm' => $confirmationArray
        ]);
    }

}