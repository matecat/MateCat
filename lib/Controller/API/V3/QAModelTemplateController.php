<?php

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Klein\Response;
use Model\LQA\QAModelTemplate\QAModelTemplateDao;
use Swaggest\JsonSchema\InvalidValue;
use Utils\Registry\AppConfig;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;
use Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;


class QAModelTemplateController extends KleinController {

    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @param $json
     *
     * @throws InvalidValue
     * @throws JSONValidatorException
     * @throws \Swaggest\JsonSchema\Exception
     * @throws JsonValidatorGenericException
     */
    private function validateJSON( $json ) {
        $validatorObject       = new JSONValidatorObject();
        $validatorObject->json = $json;
        $jsonSchema            = file_get_contents( AppConfig::$ROOT . '/inc/validation/schema/qa_model.json' );
        $validator             = new JSONValidator( $jsonSchema, true );
        $validator->validate( $validatorObject );
    }

    /**
     * fetch all
     *
     * @return Response
     * @throws Exception
     */
    public function index(): Response {

        try {

            $currentPage = $this->request->param( 'page' ) ?? 1;
            $pagination  = $this->request->param( 'perPage' ) ?? 20;

            if ( $pagination > 200 ) {
                $pagination = 200;
            }

            $uid = $this->getUser()->uid;

            return $this->response->json( QAModelTemplateDao::getAllPaginated( $uid, "/api/v3/qa_model_template?page=", (int)$currentPage, (int)$pagination ) );

        } catch ( Exception $exception ) {
            $code = ( $exception->getCode() > 0 ) ? $exception->getCode() : 500;
            $this->response->status()->setCode( $code );

            return $this->response->json( [
                    'error' => $exception->getMessage()
            ] );
        }
    }

    /**
     * create new template
     *
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

            $model = QAModelTemplateDao::createFromJSON( $json, $this->getUser()->uid );

            $this->response->code( 201 );

            return $this->response->json( $model );
        } catch ( JSONValidatorException|JsonValidatorGenericException|InvalidValue $exception ) {
            $this->response->code( 400 );

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

        try {

            $id = (int)$this->request->param( 'id' );

            $deleted = QAModelTemplateDao::remove( $id, $this->getUser()->uid );

            if ( empty( $deleted ) ) {
                throw new Exception( 'Model not found', 404 );
            }

            return $this->response->json( [
                    'id' => $id
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
     * edit model
     *
     * @return Response
     */
    public function edit(): Response {

        try {

            $id = (int)$this->request->param( 'id' );

            $model = QAModelTemplateDao::get( [
                    'id'  => $id,
                    'uid' => $this->getUser()->uid
            ] );

            if ( empty( $model ) ) {
                throw new Exception( 'Model not found', 404 );
            }

            $json = $this->request->body();

            $this->validateJSON( $json );

            $model = QAModelTemplateDao::editFromJSON( $model, $json );

            $this->response->code( 200 );

            return $this->response->json( $model );

        } catch ( JSONValidatorException|JsonValidatorGenericException|InvalidValue  $exception ) {
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
     * fetch single
     *
     * @return Response
     * @throws Exception
     */
    public function view(): Response {

        try {

            (int)$id = $this->request->param( 'id' );

            $model = QAModelTemplateDao::get( [
                    'id'  => $id,
                    'uid' => $this->getUser()->uid
            ] );

            if ( empty( $model ) ) {
                throw new Exception( 'Model not found', 404 );
            }

            $this->response->code( 200 );

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
     * This is the QA Model JSON schema
     *
     * @return Response
     */
    public function schema(): Response {
        return $this->response->json( json_decode( $this->getQaModelSchema() ) );
    }

    /**
     * Validate a QA Model template
     *
     * @return Response
     */
    public function validate(): Response {
        try {
            $json = $this->request->body();

            $validatorObject       = new JSONValidatorObject();
            $validatorObject->json = $json;
            $validator             = new JSONValidator( $this->getQaModelSchema() );
            $validator->validate( $validatorObject );

            $errors = $validator->getExceptions();
            $code   = ( $validator->isValid() ) ? 200 : 500;

            $this->response->code( $code );

            return $this->response->json( [
                    'errors' => $errors
            ] );
        } catch ( Exception $exception ) {
            $this->response->code( 500 );

            return $this->response->json( [
                    'error' => $exception->getMessage()
            ] );
        }
    }

    /**
     * @return string
     */
    private function getQaModelSchema(): string {
        return file_get_contents( AppConfig::$ROOT . '/inc/validation/schema/qa_model.json' );
    }

    /**
     * @throws Exception
     */
    public function default(): Response {

        $this->response->status()->setCode( 200 );

        return $this->response->json(
                QAModelTemplateDao::getDefaultTemplate( $this->getUser()->uid )
        );

    }

}