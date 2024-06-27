<?php

namespace API\V2;

use API\V2\Validators\LoginValidator;
use Exception;
use Klein\Response;
use PayableRates\CustomPayableRateDao;
use PayableRates\CustomPayableRateStruct;
use Validator\Errors\JSONValidatorError;
use Validator\JSONValidator;
use Validator\JSONValidatorObject;

class PayableRateController extends KleinController {
    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function index() {
        $currentPage = ( isset( $_GET[ 'page' ] ) ) ? $_GET[ 'page' ] : 1;
        $pagination  = ( isset( $_GET[ 'perPage' ] ) ) ? $_GET[ 'perPage' ] : 20;

        if ( $pagination > 200 ) {
            $pagination = 200;
        }

        $uid = $this->getUser()->uid;

        return $this->response->json( CustomPayableRateDao::getAllPaginated( $uid, $currentPage, $pagination ) );
    }

    /**
     * @return Response
     */
    public function create() {

        // accept only JSON
        if($this->request->headers()->get('Content-Type') !== 'application/json'){
            $this->response->json([
                'message' => 'Method not allowed'
            ]);
            $this->response->code(405);
            exit();
        }

        // try to create the template
        try {
            $json   = $this->request->body();
            $struct = CustomPayableRateDao::createFromJSON( $json, $this->getUser()->uid );

            $this->response->code( 201 );

            return $this->response->json( $struct );
        } catch ( JSONValidatorError $exception ) {
            $errorCode = $exception->getCode() >= 400 ? $exception->getCode() : 500;
            $this->response->code( $errorCode );

            return $this->response->json( $exception );
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
    public function delete() {

        $id    = $this->request->param( 'id' );
        $model = CustomPayableRateDao::getById( $id );

        if ( empty( $model ) ) {
            $this->response->code( 404 );

            return $this->response->json( [
                    'error' => 'Model not found'
            ] );
        }

        if ( $this->getUser()->uid !== $model->uid ) {
            $this->response->code( 401 );

            return $this->response->json( [
                    'error' => 'User not allowed'
            ] );
        }

        try {
            CustomPayableRateDao::remove( $id );

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
    public function edit() {

        $id    = $this->request->param( 'id' );
        $model = CustomPayableRateDao::getById( $id );

        if ( empty( $model ) ) {
            $this->response->code( 404 );

            return $this->response->json( [
                    'error' => 'Model not found'
            ] );
        }

        if ( $this->getUser()->uid !== $model->uid ) {
            $this->response->code( 401 );

            return $this->response->json( [
                    'error' => 'User not allowed'
            ] );
        }

        try {
            $json   = $this->request->body();
            $struct = CustomPayableRateDao::editFromJSON( $model, $json );

            $this->response->code( 200 );

            return $this->response->json( $struct );
        } catch ( JSONValidatorError $exception ) {
            $errorCode = $exception->getCode() >= 400 ? $exception->getCode() : 500;
            $this->response->code( $errorCode );

            return $this->response->json( $exception );
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
    public function view() {
        $id    = $this->request->param( 'id' );
        $model = CustomPayableRateDao::getById( $id );

        if ( empty( $model ) ) {
            $this->response->code( 404 );

            return $this->response->json( [
                    'error' => 'Model not found'
            ] );
        }

        if ( $this->getUser()->uid !== $model->uid ) {
            $this->response->code( 401 );

            return $this->response->json( [
                    'error' => 'User not allowed'
            ] );
        }

        return $this->response->json( $model );
    }

    /**
     * This is the Payable Rate Model JSON schema
     *
     * @return Response
     */
    public function schema() {
        return $this->response->json( json_decode( $this->getPayableRateModelSchema() ) );
    }

    /**
     * Validate a Payable Rate Model template
     *
     * @return Response
     */
    public function validate() {
        try {
            $json = $this->request->body();

            $validatorObject       = new JSONValidatorObject();
            $validatorObject->json = $json;
            $validator             = new JSONValidator( $this->getPayableRateModelSchema() );
            $validator->validate( $validatorObject );

            $errors = $validator->getErrors();

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
        return file_get_contents( \INIT::$ROOT . '/inc/validation/schema/payable_rate.json' );
    }
}
