<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 12/04/17
 * Time: 19.29
 *
 */

namespace Controller\API\V2;


use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\JobPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Model\Jobs\JobStruct;
use Model\Outsource\ConfirmationDao;
use Model\Translators\TranslatorsModel;
use ReflectionException;
use View\API\V2\Json\JobTranslator;

class JobsTranslatorsController extends KleinController
{

    /**
     * @var JobStruct
     * @see JobsTranslatorsController::afterConstruct method
     */
    protected JobStruct $jStruct;

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function add(): void
    {
        $this->params = filter_var_array($this->params, [
            'email' => ['filter' => FILTER_SANITIZE_EMAIL],
            'delivery_date' => ['filter' => FILTER_SANITIZE_NUMBER_INT],
            'timezone' => ['filter' => FILTER_SANITIZE_NUMBER_FLOAT, 'flags' => FILTER_FLAG_ALLOW_FRACTION],
            'id_job' => ['filter' => FILTER_SANITIZE_NUMBER_INT],
            'password' => [
                'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_BACKTICK
            ]
        ]);

        if (empty($this->params['email'])) {
            throw new InvalidArgumentException("Wrong parameter :email ", 400);
        }

        if ($this->jStruct->isDeleted()) {
            throw new NotFoundException('No job found.');
        }

        $TranslatorsModel = new TranslatorsModel($this->jStruct);
        $TranslatorsModel
            ->setUserInvite($this->user)
            ->setDeliveryDate($this->params['delivery_date'])
            ->setJobOwnerTimezone($this->params['timezone'])
            ->setEmail($this->params['email']);

        $tStruct = $TranslatorsModel->update();
        $this->response->json(
            [
                'job' => [
                    'id' => $this->jStruct->id,
                    'password' => $this->jStruct->password,
                    'translator' => (new JobTranslator($tStruct))->renderItem()
                ]
            ]
        );
    }

    /**
     * @throws ReflectionException
     * @throws \Model\Exceptions\NotFoundException
     * @throws NotFoundException
     */
    public function get(): void
    {
        $this->params = filter_var_array($this->params, [
            'id_job' => ['filter' => FILTER_SANITIZE_NUMBER_INT],
            'password' => [
                'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_BACKTICK
            ]
        ]);

        $confDao = new ConfirmationDao();
        $confirmationStruct = $confDao->setCacheTTL(60 * 60)->getConfirmation($this->jStruct);

        if (!empty($confirmationStruct)) {
            throw new InvalidArgumentException("The Job is Outsourced.", 400);
        }

        if ($this->jStruct->isDeleted()) {
            throw new NotFoundException('No job found.');
        }

        //do not show outsourced translators
        $outsourceInfo = $this->jStruct->getOutsource();
        $tStruct = $this->jStruct->getTranslator();
        $translator = null;
        if (empty($outsourceInfo)) {
            $translator = (!empty($tStruct) ? (new JobTranslator($tStruct))->renderItem() : null);
        }
        $this->response->json(
            [
                'job' => [
                    'id' => $this->jStruct->id,
                    'password' => $this->jStruct->password,
                    'translator' => $translator
                ]
            ]
        );
    }

    /**
     */
    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
        $validator = new JobPasswordValidator($this);
        $validator->onSuccess(function () use ($validator) {
            $this->jStruct = $validator->getJob();
        });

        $this->appendValidator($validator);
    }

}