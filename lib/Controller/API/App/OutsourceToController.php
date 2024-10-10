<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use OutsourceTo_Translated;

class OutsourceToController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function outsource()
    {
        $pid = filter_var( $this->request->param( 'pid' ), FILTER_SANITIZE_STRING );
        $ppassword = filter_var( $this->request->param( 'ppassword' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $currency = filter_var( $this->request->param( 'currency' ), FILTER_SANITIZE_STRING );
        $timezone = filter_var( $this->request->param( 'timezone' ), FILTER_SANITIZE_STRING );
        $fixedDelivery = filter_var( $this->request->param( 'fixedDelivery' ), FILTER_SANITIZE_NUMBER_INT );
        $typeOfService = filter_var( $this->request->param( 'typeOfService' ), FILTER_SANITIZE_STRING );
        $jobList = filter_var( $this->request->param( 'jobs' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_REQUIRE_ARRAY  | FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );

        if( empty( $pid ) ){
            $this->response->code(400);

            return $this->response->json([
                "code" => -1,
                "message" => "No id project provided"
            ]);
        }

        if( empty( $ppassword ) ){
            $this->response->code(400);

            return $this->response->json([
                "code" => -2,
                "message" => "No project Password Provided"
            ]);
        }

        /**
         * The Job List form
         *
         * <pre>
         * Ex:
         *   array(
         *      0 => array(
         *          'id' => 5901,
         *          'jpassword' => '6decb661a182',
         *      ),
         *   );
         * </pre>
         */
        if( empty( $jobList ) ){
            $this->response->code(400);

            return $this->response->json([
                "code" => -3,
                "message" => "No job list Provided"
            ]);
        }

        if ( empty( $currency ) ) {
            $currency = @$_COOKIE[ "matecat_currency" ];
        }

        if ( empty( $timezone ) and $timezone !== "0" ) {
            $timezone = @$_COOKIE[ "matecat_timezone" ];
        }

        if ( !in_array( $typeOfService, array( "premium" , "professional") ) ) {
            $typeOfService = "professional";
        }

        $outsourceTo = new OutsourceTo_Translated();
        $outsourceTo->setPid( $pid )
            ->setPpassword( $ppassword )
            ->setCurrency( $currency )
            ->setTimezone( $timezone )
            ->setJobList( $jobList )
            ->setFixedDelivery( $fixedDelivery )
            ->setTypeOfService( $typeOfService )
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
        $client_output = $outsourceTo->getQuotesResult();

        $this->response->json( [
            'data' => array_values( $client_output ),
            'return_url' => [
                'url_ok'          => $outsourceTo->getOutsourceLoginUrlOk(),
                'url_ko'          => $outsourceTo->getOutsourceLoginUrlKo(),
                'confirm_urls'    => $outsourceTo->getOutsourceConfirm(),
            ]
        ] );
    }
}