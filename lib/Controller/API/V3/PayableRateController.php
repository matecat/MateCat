<?php

namespace API\V3;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use Exception;
use INIT;
use Klein\Response;
use PayableRates\CustomPayableRateDao;
use PayableRates\CustomPayableRateStruct;
use Swaggest\JsonSchema\InvalidValue;
use Validator\Errors\JSONValidatorException;
use Validator\Errors\JsonValidatorGenericException;
use Validator\JSONValidator;
use Validator\JSONValidatorObject;

class PayableRateController extends KleinController {
    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @return Response
     */
    public function index(): Response {

        try {

            $currentPage = $this->request->param( 'page' ) ?? 1;
            $pagination  = $this->request->param( 'perPage' ) ?? 20;

            if ( $pagination > 200 ) {
                $pagination = 200;
            }

            $uid = $this->getUser()->uid;

            return $this->response->json( CustomPayableRateDao::getAllPaginated( $uid, "/api/v3/payable_rate?page=", (int)$currentPage, (int)$pagination ) );

        } catch ( Exception $exception ) {
            $code = ( $exception->getCode() > 0 ) ? $exception->getCode() : 500;
            $this->response->status()->setCode( $code );

            return $this->response->json( [
                    'error' => $exception->getMessage()
            ] );
        }

    }

    /**
     * @return Response
     */
    public function create(): Response {

        // try to create the template
        try {

            // accept only JSON
            if ( !$this->isJsonRequest() ) {
                throw new Exception( 'Method not allowed', 405 );
            }

            $json = $this->request->body();
            $this->validateJSON( $json );

            $struct = CustomPayableRateDao::createFromJSON( $json, $this->getUser()->uid );

            $this->response->code( 201 );

            return $this->response->json( $struct );
        } catch ( JSONValidatorException|JsonValidatorGenericException|InvalidValue $exception ) {
            $errorCode = max( $exception->getCode(), 400 );
            $this->response->code( $errorCode );

            return $this->response->json( [ 'error' => $exception->getMessage() ] );
        } catch ( Exception $exception ) {
            $errorCode = $exception->getCode() >= 400 ? $exception->getCode() : 500;
            $this->response->code( $errorCode );

            return $this->response->json( [
                    'error' => $exception->getMessage()
            ] );
        }
    }

    /**
     * @return Response
     */
    public function delete(): Response {

        $id = $this->request->param( 'id' );

        try {

            $count = CustomPayableRateDao::remove( $id, $this->getUser()->uid );

            if ( $count == 0 ) {
                throw new Exception( 'Model not found', 404 );
            }

            return $this->response->json( [
                    'id' => (int)$id
            ] );
        } catch ( Exception $exception ) {
            $errorCode = $exception->getCode() >= 400 ? $exception->getCode() : 500;
            $this->response->code( $errorCode );

            return $this->response->json( [
                    'error' => $exception->getMessage()
            ] );
        }
    }

    /**
     * @return Response
     */
    public function edit(): Response {

        try {

            // accept only JSON
            if ( !$this->isJsonRequest() ) {
                throw new Exception( 'Bad Request', 400 );
            }

            $id = $this->request->param( 'id' );

            $model = CustomPayableRateDao::getByIdAndUser( $id, $this->getUser()->uid );
            if ( empty( $model ) ) {
                throw new Exception( 'Model not found', 404 );
            }

            $json = $this->request->body();
            $this->validateJSON( $json );

            $struct = CustomPayableRateDao::editFromJSON( $model, $json );

            $this->response->code( 200 );

            return $this->response->json( $struct );

        } catch ( JSONValidatorException|JsonValidatorGenericException|InvalidValue $exception ) {
            $errorCode = max( $exception->getCode(), 400 );
            $this->response->code( $errorCode );

            return $this->response->json( [ 'error' => $exception->getMessage() ] );
        } catch ( Exception $exception ) {
            $errorCode = $exception->getCode() >= 400 ? $exception->getCode() : 500;
            $this->response->code( $errorCode );

            return $this->response->json( [
                    'error' => $exception->getMessage()
            ] );
        }
    }

    /**
     * @return Response
     */
    public function view(): Response {

        try {

            $id    = $this->request->param( 'id' );
            $model = CustomPayableRateDao::getByIdAndUser( $id, $this->getUser()->uid );

            if ( empty( $model ) ) {
                throw new Exception( 'Model not found', 404 );
            }

            return $this->response->json( $model );

        } catch ( Exception $exception ) {
            $errorCode = $exception->getCode() >= 400 ? $exception->getCode() : 500;
            $this->response->code( $errorCode );

            return $this->response->json( [
                    'error' => $exception->getMessage()
            ] );
        }

    }

    /**
     * This is the Payable Rate Model JSON schema
     *
     * @return Response
     */
    public function schema(): Response {
        return $this->response->json( json_decode( $this->getPayableRateModelSchema() ) );
    }

    /**
     * Validate a Payable Rate Model template
     *
     * @return Response
     */
    public function validate(): Response {
        try {
            $json = $this->request->body();

            $validatorObject       = new JSONValidatorObject();
            $validatorObject->json = $json;
            $validator             = new JSONValidator( $this->getPayableRateModelSchema() );
            $validator->validate( $validatorObject );

            $errors = $validator->getExceptions();

            if ( $validator->isValid() ) {
                $customPayableRateStruct = new CustomPayableRateStruct();
                $customPayableRateStruct->hydrateFromJSON( $json );
            }

            $code = ( $validator->isValid() ) ? 200 : 500;

            $this->response->code( $code );

            return $this->response->json( [
                    'errors' => $errors
            ] );
        } catch ( Exception $exception ) {
            $errorCode = $exception->getCode() >= 400 ? $exception->getCode() : 500;
            $this->response->code( $errorCode );

            return $this->response->json( [
                    'error' => $exception->getMessage()
            ] );
        }
    }

    /**
     * @return false|string
     */
    private function getPayableRateModelSchema() {
        return file_get_contents( INIT::$ROOT . '/inc/validation/schema/payable_rate.json' );
    }

    /**
     * @throws Exception
     */
    public function default() {

        $this->response->status()->setCode( 200 );
        $this->response->json(
                CustomPayableRateDao::getDefaultTemplate( $this->getUser()->uid )
        );

    }

    /**
     * @param string $json
     *
     * @throws InvalidValue
     * @throws JSONValidatorException
     * @throws \Swaggest\JsonSchema\Exception
     * @throws JsonValidatorGenericException
     */
    private static function validateJSON( string $json ) {
        $validatorObject       = new JSONValidatorObject();
        $validatorObject->json = $json;
        $jsonSchema            = file_get_contents( INIT::$ROOT . '/inc/validation/schema/payable_rate.json' );
        $validator             = new JSONValidator( $jsonSchema, true );
        $validator->validate( $validatorObject );
    }

}
