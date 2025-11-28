<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use InvalidArgumentException;
use ReflectionException;
use Utils\OutsourceTo\Translated;

class OutsourceToController extends KleinController
{

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws ReflectionException
     */
    public function outsource(): void
    {
        $request       = $this->validateTheRequest();
        $pid           = $request[ 'pid' ];
        $ppassword     = $request[ 'ppassword' ];
        $currency      = $request[ 'currency' ];
        $timezone      = $request[ 'timezone' ];
        $fixedDelivery = $request[ 'fixedDelivery' ];
        $typeOfService = $request[ 'typeOfService' ];
        $jobList       = $request[ 'jobList' ];

        $outsourceTo = new Translated();
        $outsourceTo->setPid($pid)
                ->setPpassword($ppassword)
                ->setCurrency($currency)
                ->setTimezone($timezone)
                ->setJobList($jobList)
                ->setFixedDelivery($fixedDelivery)
                ->setTypeOfService($typeOfService)
                ->setUser($this->user)
                ->setFeatures($this->featureSet)
                ->performQuote();

        /*
         * Example:
         *
         *   $client_output = array (
         *       '5901-6decb661a182' =>
         *               array (
         *                       'id' => '5901-6decb661a182',
         *                       'quantity' => '1',
         *                       'name' => 'MATECAT_5901-6decb661a182',
         *                       'quote_pid' => '11180933',
         *                       'source' => 'it-IT',
         *                       'target' => 'en-GB',
         *                       'price' => '12.00',
         *                       'words' => '120',
         *                       'show_info' => '0',
         *                       'delivery_date' => '2014-04-29T15:00:00Z',
         *               ),
         *   );
         */
        $this->response->json([
                'code'       => 1,
                'errors'     => [],
                'data'       => array_values($outsourceTo->getQuotesResult()),
                'return_url' => [
                        'url_ok'       => $outsourceTo->getOutsourceLoginUrlOk(),
                        'url_ko'       => $outsourceTo->getOutsourceLoginUrlKo(),
                        'confirm_urls' => $outsourceTo->getOutsourceConfirmUrl(),
                ]
        ]);
    }

    /**
     * @return array
     */
    private function validateTheRequest(): array
    {
        $pid           = filter_var($this->request->param('pid'), FILTER_SANITIZE_SPECIAL_CHARS);
        $ppassword     = filter_var($this->request->param('ppassword'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $currency      = filter_var($this->request->param('currency'), FILTER_SANITIZE_SPECIAL_CHARS);
        $timezone      = filter_var($this->request->param('timezone'), FILTER_SANITIZE_SPECIAL_CHARS);
        $fixedDelivery = filter_var($this->request->param('fixedDelivery'), FILTER_SANITIZE_NUMBER_INT);
        $typeOfService = filter_var($this->request->param('typeOfService'), FILTER_SANITIZE_SPECIAL_CHARS);
        $jobList       = filter_var($this->request->param('jobs'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_REQUIRE_ARRAY | FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);

        if (empty($pid)) {
            throw new InvalidArgumentException("No id project provided", -1);
        }

        if (empty($ppassword)) {
            throw new InvalidArgumentException("No project Password Provided", -2);
        }

        /**
         * The Job List form
         *
         * <pre>
         * Ex:
         *   [
         *      0 => [
         *          'id' => 5901,
         *          'jpassword' => '6decb661a182',
         *      ],
         *   ];
         * </pre>
         */
        if (empty($jobList)) {
            throw new InvalidArgumentException("No job list Provided", -3);
        }

        if (empty($currency)) {
            $currency = $_COOKIE[ "matecat_currency" ] ?? null;
        }

        if (empty($timezone) and $timezone !== "0") {
            $timezone = $_COOKIE[ "matecat_timezone" ] ?? null;
        }

        if (!in_array($typeOfService, ["premium", "professional"])) {
            $typeOfService = "professional";
        }

        return [
                'pid'           => $pid,
                'ppassword'     => $ppassword,
                'currency'      => $currency,
                'timezone'      => $timezone,
                'fixedDelivery' => $fixedDelivery,
                'typeOfService' => $typeOfService,
                'jobList'       => $jobList,
        ];
    }
}