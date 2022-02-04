<?php

namespace API\V3;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use API\V2\Validators\TeamAccessValidator;


class QAModelTemplateController extends KleinController {

    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function index() {

        // check if user is allowed to access to this feature
    }

    public function create() {}

    public function delete() {}

    public function edit() {}

    public function view() {}

    /**
     * This is the QA Model JSON schema
     */
    public function schema()
    {
        $this->response->json(json_decode($this->getQaModelSchema()));
    }

    public function validate()
    {
        // accept only JSON
        if($this->request->headers()->get('Content-Type') !== 'application/json'){
            $this->response->json([
                'message' => 'Method not allowed'
            ]);
            $this->response->code(405);
            exit();
        }

        try {
            $json = $this->request->body();
            $validator = new \Validator\JSONValidator($this->getQaModelSchema());
            $validate = $validator->validate($json);
            $errors = (!empty($validate)) ? $validate : [];

            return $this->response->json([
                'errors' => $errors
            ]);
        } catch (\Exception $exception){
            $this->response->json([
                'error' => $exception->getMessage()
            ]);
            $this->response->code(500);
            exit();
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