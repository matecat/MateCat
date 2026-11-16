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
use Controller\API\Commons\Exceptions\ConflictError;
use Controller\API\Commons\JobRestructureLock;
use Exception;
use Model\Jobs\JobDao;
use Model\Outsource\ConfirmationDao;
use Model\Outsource\TranslatedConfirmationStruct;
use Model\Translators\TranslatorsModel;
use Predis\PredisException;
use Random\RandomException;
use ReflectionException;
use Throwable;
use Utils\Registry\AppConfig;
use Utils\Tools\SimpleJWT;

class OutsourceConfirmationController extends AbstractStatefulKleinController
{

    /**
     * @throws AuthorizationError
     * @throws ConflictError when a split, a merge or a password change holds the job
     * @throws ReflectionException
     * @throws PredisException
     * @throws RandomException
     * @throws Throwable
     */
    public function confirm(): void
    {
        $params = filter_var_array($this->request->params(), [
            'id_job' => FILTER_SANITIZE_SPECIAL_CHARS,
            'password' => FILTER_SANITIZE_SPECIAL_CHARS,
            'payload' => FILTER_SANITIZE_SPECIAL_CHARS,
        ]);

        $payload = SimpleJWT::getValidatedInstanceFromString(
            (string)$params['payload'],
            AppConfig::$AUTHSECRET
        )->getPayload();

        if ($params['id_job'] != $payload['id_job'] || $params['password'] != $payload['password']) {
            throw new AuthorizationError("Invalid job");
        }

        $jStruct = (new JobDao($this->getDatabase()))->getByIdAndPassword((int)$params['id_job'], (string)$params['password']);
        if ($jStruct === null) {
            throw new AuthorizationError("Job not found");
        }
        $translatorModel = new TranslatorsModel($jStruct, $this->getDatabase(), 0);
        $jTranslatorStruct = $translatorModel->getTranslator();

        $confirmationStruct = new TranslatedConfirmationStruct($payload);

        if (!empty($jTranslatorStruct)) {
            // Serialized with the split, merge and password change of the same job, which rotate
            // the same password.
            $this->restructureLock()->run(
                (int)$jStruct->id,
                fn() => $translatorModel->changeJobPassword()
            );
            $confirmationStruct->password = $jStruct->password;
        }

        $confirmationStruct->create_date = date(DATE_ATOM, time());
        $cDao = new ConfirmationDao($this->getDatabase());
        $cDao->insertStruct($confirmationStruct, ['ignore' => true, 'no_nulls' => true]);
        $cDao->destroyConfirmationCache($jStruct);

        $confirmationArray = $confirmationStruct->toArray();
        unset($confirmationArray['id']);

        $this->response->json([
            'errors' => [],
            'confirm' => $confirmationArray
        ]);
    }

    /**
     * Seam: the per-job lock confirm() takes. Overridable in tests to keep them off Redis.
     *
     * @throws Exception when the Redis connection cannot be configured
     */
    protected function restructureLock(): JobRestructureLock
    {
        return JobRestructureLock::forDefaultConnection();
    }

}