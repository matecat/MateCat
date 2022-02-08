<?php

namespace API\V3;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use API\V2\Validators\TeamAccessValidator;
use QAModelTemplate\QAModelTemplateDao;
use Validator\Errors\JSONValidatorError;


class QAModelTemplateController extends KleinController {

    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * fetch all
     *
     * @return \Klein\Response
     */
    public function index()
    {
        $models = QAModelTemplateDao::getAll();

        return $this->response->json($models);
    }

    /**
     * create new template
     *
     * @return \Klein\Response
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
            $json = $this->request->body();
            $id = QAModelTemplateDao::createFromJSON($json, $this->getUser()->uid);

            $this->response->code(201);
            return $this->response->json([
                'id' => $id
            ]);
        } catch (JSONValidatorError $exception){
            $this->response->code(500);

            return $this->response->json($exception);
        } catch (\Exception $exception){
            $this->response->code(500);

            return $this->response->json([
                'error' => $exception->getMessage()
            ]);
        }
    }

    /**
     * @param $id
     *
     * @return \Klein\Response
     */
    public function delete($id)
    {
        try {
            QAModelTemplateDao::remove($id);
            $this->response->code(200);

            return $this->response->json([
                    'id' => $id
            ]);
        } catch (\Exception $exception){
            $this->response->code(500);

            return $this->response->json([
                'error' => $exception->getMessage()
            ]);
        }
    }

    public function edit($id)
    {
        $model = QAModelTemplateDao::get($id);

        if(empty($model)){
            $this->response->code(404);

            return $this->response->json([
                    'error' => 'Model not found'
            ]);
        }

        // fai cose e scrivi un json

        // salva
    }

    /**
     * fetch single
     *
     * @return \Klein\Response
     */
    public function view()
    {
        $id = $this->request->param( 'id' );
        $model = QAModelTemplateDao::get($id);

        if(!empty($model)){
            return $this->response->json($model);
        }

        $this->response->code(404);

        return $this->response->json([
                'error' => 'Model not found'
        ]);
    }

    /**
     * This is the QA Model JSON schema
     *
     * @return \Klein\Response
     */
    public function schema()
    {
        return $this->response->json(json_decode($this->getQaModelSchema()));
    }

    /**
     * Validate a QA Model template
     *
     * @return \Klein\Response
     */
    public function validate()
    {
        try {
            $json = $this->request->body();
            $validator = new \Validator\JSONValidator($this->getQaModelSchema());
            $validate = $validator->validate($json);
            $errors = (!empty($validate)) ? $validate : [];
            $code = (!empty($validate)) ? 500 : 200;

            $this->response->code($code);
            return $this->response->json([
                'errors' => $errors
            ]);
        } catch (\Exception $exception){
            $this->response->code(500);

            return $this->response->json([
                    'error' => $exception->getMessage()
            ]);
        }
    }

    /**
     * @return false|string
     */
    private function getQaModelSchema()
    {
        return file_get_contents( \INIT::$ROOT . '/inc/qa_model/schema.json' );
    }
}