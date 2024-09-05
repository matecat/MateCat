<?php

namespace API\V3;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use Exception;
use INIT;
use Klein\Response;
use PDOException;
use Swaggest\JsonSchema\InvalidValue;
use Validator\Errors\JSONValidatorException;
use Validator\Errors\JsonValidatorGenericException;
use Validator\JSONValidator;
use Validator\JSONValidatorObject;
use Xliff\XliffConfigTemplateDao;

class XliffConfigTemplateController extends KleinController {
    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @param $json
     *
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws InvalidValue
     * @throws \Swaggest\JsonSchema\Exception
     */
    private function validateJSON( $json ) {
        $validatorObject       = new JSONValidatorObject();
        $validatorObject->json = $json;
        $jsonSchema            = file_get_contents( INIT::$ROOT . '/inc/validation/schema/xliff_parameters_rules_wrapper.json' );
        $validator             = new JSONValidator( $jsonSchema, true );
        $validator->validate( $validatorObject );
    }

    /**
     * Get all entries
     */
    public function all(): Response {

        try {

            $currentPage = $this->request->param( 'page' ) ?? 1;
            $pagination  = $this->request->param( 'perPage' ) ?? 20;

            if ( $pagination > 200 ) {
                $pagination = 200;
            }

            $uid = $this->getUser()->uid;

            $this->response->status()->setCode( 200 );

            return $this->response->json( XliffConfigTemplateDao::getAllPaginated( $uid, "/api/v3/xliff-config-template?page=", (int)$currentPage, (int)$pagination ) );

        } catch ( Exception $exception ) {
            $code = ( $exception->getCode() > 0 ) ? $exception->getCode() : 500;
            $this->response->status()->setCode( $code );

            return $this->response->json( [
                    'error' => $exception->getMessage()
            ] );
        }
    }

    /**
     * Get a single entry
     */
    public function get(): Response {

        try {

            $id = (int)$this->request->param( 'id' );

            $model = XliffConfigTemplateDao::getByIdAndUser( $id, $this->getUser()->uid );

            if ( empty( $model ) ) {
                throw new Exception( 'Model not found', 404 );
            }

            $this->response->status()->setCode( 200 );

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
     * Create new entry
     *
     * @return Response
     */
    public function create(): Response {

        // try to create the template
        try {

            // accept only JSON
            if ( !$this->isJsonRequest() ) {
                throw new Exception( 'Bad Request', 400 );
            }

            $json = $this->request->body();
            $this->validateJSON( $json );

            $struct = XliffConfigTemplateDao::createFromJSON( $json, $this->getUser()->uid );
            $this->response->code( 201 );

            return $this->response->json( $struct );

        } catch ( JSONValidatorException|JsonValidatorGenericException|InvalidValue $exception ) {
            $errorCode = max( $exception->getCode(), 400 );
            $this->response->code( $errorCode );

            return $this->response->json( [ 'error' => $exception->getMessage() ] );
        } catch ( PDOException $e ) {
            if ( $e->getCode() == 23000 ) {
                $this->response->code( 400 );

                return $this->response->json( [
                        'error' => "Invalid unique template name"
                ] );
            } else {
                $this->response->code( 500 );

                return $this->response->json( [
                        'error' => $e->getMessage()
                ] );
            }
        } catch ( Exception $exception ) {

            $errorCode = $exception->getCode() >= 400 ? $exception->getCode() : 500;
            $this->response->code( $errorCode );

            return $this->response->json( [
                    'error' => $exception->getMessage()
            ] );
        }
    }

    /**
     * Update an entry
     *
     * @return Response
     * @throws Exception
     */
    public function update(): Response {

        try {

            // accept only JSON
            if ( !$this->isJsonRequest() ) {
                throw new Exception( 'Bad Request', 400 );
            }

            $id  = (int)$this->request->param( 'id' );
            $uid = $this->getUser()->uid;

            $json = $this->request->body();
            $this->validateJSON( $json );

            $model = XliffConfigTemplateDao::getByIdAndUser( $id, $uid );

            if ( empty( $model ) ) {
                throw new Exception( 'Model not found', 404 );
            }

            $struct = XliffConfigTemplateDao::editFromJSON( $model, $json, $uid );

            $this->response->code( 200 );

            return $this->response->json( $struct );
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
     * Delete an entry
     */
    public function delete(): Response {

        try {

            $id  = (int)$this->request->paramsNamed()->get('id');
            $uid = $this->getUser()->uid;

            $count = XliffConfigTemplateDao::remove( $id, $uid );

            if ( $count == 0 ) {
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
     * @return Response
     */
    public function schema(): Response {
        return $this->response->json( $this->getModelSchema() );
    }

    /**
     * @return object|mixed
     */
    private function getModelSchema(): object {
        $skeletonSchema = JSONValidator::getValidJSONSchema( file_get_contents( INIT::$ROOT . '/inc/validation/schema/xliff_parameters_rules_wrapper.json' ) );
        $contentSchema  = JSONValidator::getValidJSONSchema( file_get_contents( INIT::$ROOT . '/inc/validation/schema/xliff_parameters_rules_content.json' ) );

        $skeletonSchema->properties->rules->properties = $contentSchema->properties;
        $skeletonSchema->definitions                   = $contentSchema->definitions;

        return $skeletonSchema;
    }
}