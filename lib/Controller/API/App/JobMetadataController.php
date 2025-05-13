<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\ChunkPasswordValidator;
use API\Commons\Validators\LoginValidator;
use Exception;
use Jobs\MetadataDao;
use Utils;

class JobMetadataController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
        $this->appendValidator( new ChunkPasswordValidator( $this ) );
    }

    /**
     * Delete metadata by key
     */
    public function delete()
    {
        $params = $this->sanitizeRequestParams();
        $dao = new MetadataDao();

        try {
            $struct = $dao->get($params['id_job'], $params['password'], $params['key']);

            if(empty($struct)){
                throw new Exception( 'Metadata not found', 400 );
            }

            $dao->delete($params['id_job'], $params['password'], $params['key']);
            $this->response->json([
                'id' => $struct->id
            ]);
        } catch (Exception $exception){
            $this->response->status()->setCode( $exception->getCode() >= 400 ? $exception->getCode() : 500 );
            $this->response->json( [
                'error' => $exception->getMessage()
            ] );
        }
    }

    /**
     * Get all job metadata
     */
    public function get()
    {
        $params = $this->sanitizeRequestParams();
        $dao = new MetadataDao();

        try {
            $this->response->json( $dao->getByJobIdAndPassword($params['id_job'], $params['password']) );
        } catch (Exception $exception){
            $this->response->status()->setCode( $exception->getCode() >= 400 ? $exception->getCode() : 500 );
            $this->response->json( [
                'error' => $exception->getMessage()
            ] );
        }
    }

    /**
     * Upsert metadata
     */
    public function save()
    {
        $params = $this->sanitizeRequestParams();
        $dao = new MetadataDao();
        $json = $this->request->body();

        try {
            // accept only JSON
            if ( !$this->isJsonRequest() ) {
                throw new Exception( 'Bad request', 400 );
            }

            $return = [];
            $json = json_decode($json, true);

            if(!Utils::arrayIsList($json)){
                throw new Exception( 'JSON is not a list of metadata', 400 );
            }

            foreach ($json as $item){
                if(!isset($item['key'])){
                    throw new Exception( 'Missing `key` property', 400 );
                }

                if(empty($item['key'])){
                    throw new Exception( 'Empty `key` property', 400 );
                }

                if(!isset($item['value'])){
                    throw new Exception( 'Missing `value` property', 400 );
                }

                if($item['value'] === ""){
                    throw new Exception( 'Empty `value` property', 400 );
                }

                $struct = $dao->set($params['id_job'], $params['password'], $item['key'], $item['value']);
                $return[] = $struct;
            }

            $this->response->json($return);

        } catch (Exception $exception){
            $this->response->status()->setCode( $exception->getCode() >= 400 ? $exception->getCode() : 500 );
            $this->response->json( [
                'error' => $exception->getMessage()
            ] );
        }
    }

    /**
     * @return mixed
     */
    private function sanitizeRequestParams()
    {
        return filter_var_array( $this->request->params(), [
            'id_job'   => FILTER_SANITIZE_STRING,
            'password' => FILTER_SANITIZE_STRING,
            'key'      => FILTER_SANITIZE_STRING,
        ]);
    }
}