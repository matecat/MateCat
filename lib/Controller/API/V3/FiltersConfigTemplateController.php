<?php

namespace API\V3;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use Exception;
use Filters\FiltersConfigTemplateDao;
use INIT;
use Klein\Response;
use PDOException;
use Swaggest\JsonSchema\InvalidValue;
use Validator\Errors\JSONValidatorException;
use Validator\JSONValidator;
use Validator\JSONValidatorObject;

class FiltersConfigTemplateController extends KleinController {
    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @param $json
     *
     * @throws InvalidValue
     * @throws Exception
     */
    private function validateJSON( $json ) {
        $validatorObject       = new JSONValidatorObject();
        $validatorObject->json = $json;
        $jsonSchema            = file_get_contents( INIT::$ROOT . '/inc/validation/schema/filters_extraction_parameters.json' );
        $validator             = new JSONValidator( $jsonSchema );
        $validator->validate( $validatorObject );

        if ( !$validator->isValid() ) {
            throw $validator->getExceptions()[ 0 ]->error;
        }
    }

    /**
     * Get all entries
     */
    public function all() {
        $currentPage = ( isset( $_GET[ 'page' ] ) ) ? $_GET[ 'page' ] : 1;
        $pagination  = ( isset( $_GET[ 'perPage' ] ) ) ? $_GET[ 'perPage' ] : 20;

        if ( $pagination > 200 ) {
            $pagination = 200;
        }

        $uid = $this->getUser()->uid;

        try {
            $this->response->status()->setCode( 200 );

            return $this->response->json( FiltersConfigTemplateDao::getAllPaginated( $uid, $currentPage, $pagination ) );

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
    public function get() {
        $id = filter_var( $this->request->id, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_ENCODE_LOW );

        try {
            $model = FiltersConfigTemplateDao::getByIdAndUser( $id, $this->getUser()->uid );

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
    public function create() {
        // accept only JSON
        if ( !$this->isJsonRequest() ) {
            $this->response->code( 400 );

            return $this->response->json( [
                    'message' => 'Bad Request'
            ] );
        }

        // try to create the template
        try {

            $json = $this->request->body();
            $this->validateJSON( $json );

            try {
                $struct = FiltersConfigTemplateDao::createFromJSON( $json, $this->getUser()->uid );
            } catch ( PDOException $e ) {
                if ( $e->getCode() == 23000 ) {
                    $this->response->code( 404 );

                    return $this->response->json( [
                            'error' => "Invalid unique template name"
                    ] );
                } else {
                    throw $e;
                }
            }

            $this->response->code( 201 );

            return $this->response->json( $struct );

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
     * Update an entry
     *
     * @return Response
     * @throws Exception
     */
    public function update() {
        // accept only JSON
        if ( !$this->isJsonRequest() ) {
            $this->response->code( 400 );

            return $this->response->json( [
                    'message' => 'Bad Request'
            ] );
        }

        $id  = $this->request->param( 'id' );
        $uid = $this->getUser()->uid;

        $model = FiltersConfigTemplateDao::getByIdAndUser( $id, $uid );

        if ( empty( $model ) ) {
            throw new Exception( 'Model not found', 404 );
        }

        try {
            $json = $this->request->body();
            $this->validateJSON( $json );

            $struct = FiltersConfigTemplateDao::editFromJSON( $model, $json, $uid );

            $this->response->code( 200 );

            return $this->response->json( $struct );
        } catch ( JSONValidatorException $exception ) {
            $errorCode = max( $exception->getCode(), 400 );
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
     * Delete an entry
     */
    public function delete() {
        $id  = filter_var( $this->request->id, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_ENCODE_LOW );
        $uid = $this->getUser()->uid;

        try {

            $count = FiltersConfigTemplateDao::remove( $id, $uid );

            if ( $count == 0 ) {
                throw new Exception( 'Model not found', 404 );
            }

            return $this->response->json( [
                    'id' => (int)$id
            ] );

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
    public function schema() {
        return $this->response->json( $this->getModelSchema() );
    }

    /**
     * @return false|string
     */
    private function getModelSchema() {
        return json_decode( file_get_contents( INIT::$ROOT . '/inc/validation/schema/filters_xliff_config_template.json' ) );
    }
}