<?php

namespace API\V3;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use Database;
use Exception;
use INIT;
use Klein\Response;
use QAModelTemplate\QAModelTemplateDao;
use Validator\Errors\JSONValidatorException;
use Validator\JSONValidator;
use Validator\JSONValidatorObject;


class QAModelTemplateController extends KleinController {

    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * fetch all
     *
     * @return Response
     * @throws Exception
     */
    public function index(): Response {

        $currentPage = $this->request->param( 'page' ) ?? 1;
        $pagination  = $this->request->param( 'perPage' ) ?? 20;

        if ( $pagination > 200 ) {
            $pagination = 200;
        }

        $uid = $this->getUser()->uid;

        return $this->response->json( QAModelTemplateDao::getAllPaginated( $uid, $currentPage, $pagination ) );
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

            $json  = $this->request->body();
            $model = QAModelTemplateDao::createFromJSON( $json, $this->getUser()->uid );

            $this->response->code( 201 );

            return $this->response->json( $model );
        } catch ( JSONValidatorException $exception ) {
            $this->response->code( 400 );

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
    public function delete(): Response {
        $id = $this->request->param( 'id' );

        try {

            $model = QAModelTemplateDao::getQaModelTemplateByIdAndUid( Database::obtain()->getConnection(), [
                    'id'  => $id,
                    'uid' => $this->getUser()->uid
            ] );

            if ( empty( $model ) ) {
                throw new Exception( 'Model not found', 404 );
            }

            QAModelTemplateDao::remove( $id, $this->getUser()->uid );

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
     * edit model
     *
     * @return Response
     */
    public function edit(): Response {

        try {

            $id    = $this->request->param( 'id' );
            $model = QAModelTemplateDao::get( [
                    'id'  => $id,
                    'uid' => $this->getUser()->uid
            ] );

            if ( empty( $model ) ) {
                throw new Exception( 'Model not found', 404 );
            }

            $json  = $this->request->body();
            $model = QAModelTemplateDao::editFromJSON( $model, $json );

            $this->response->code( 200 );

            return $this->response->json( $model );
        } catch ( JSONValidatorException $exception ) {
            $this->response->code( 400 );

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
     * fetch single
     *
     * @return Response
     */
    public function view(): Response {
        $id    = $this->request->param( 'id' );
        $model = QAModelTemplateDao::get( [
                'id'  => $id,
                'uid' => $this->getUser()->uid
        ] );

        if ( !empty( $model ) ) {
            return $this->response->json( $model );
        }

        $this->response->code( 404 );

        return $this->response->json( [
                'error' => 'Model not found'
        ] );
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
     * @return false|string
     */
    private function getQaModelSchema() {
        return file_get_contents( INIT::$ROOT . '/inc/validation/schema/qa_model.json' );
    }

    /**
     * @throws Exception
     */
    public function default() {

        $this->response->status()->setCode( 200 );
        $this->response->json(
                QAModelTemplateDao::getDefaultTemplate( $this->getUser()->uid )
        );

    }

}