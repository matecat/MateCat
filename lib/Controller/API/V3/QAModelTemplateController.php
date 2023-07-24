<?php

namespace API\V3;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
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
        $currentPage = (isset($_GET['page'])) ? $_GET['page'] : 1;
        $pagination = 20;
        $uid = $this->getUser()->uid;

        return $this->response->json(QAModelTemplateDao::getAllPaginated($uid, $currentPage, $pagination));
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
    public function delete()
    {
        $id = $this->request->param( 'id' );
        $model = QAModelTemplateDao::get([
            'id' => $id,
            'uid' => $this->getUser()->uid
        ]);

        if(empty($model)){
            $this->response->code(404);

            return $this->response->json([
                    'error' => 'Model not found'
            ]);
        }

        try {
            QAModelTemplateDao::remove($id);

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

    /**
     * edit model
     *
     * @return \Klein\Response
     */
    public function edit()
    {
        $id = $this->request->param( 'id' );
        $model = QAModelTemplateDao::get([
            'id' => $id,
            'uid' => $this->getUser()->uid
        ]);

        if(empty($model)){
            $this->response->code(404);

            return $this->response->json([
                    'error' => 'Model not found'
            ]);
        }

        try {
            $json = $this->request->body();
            $id = QAModelTemplateDao::editFromJSON($model, $json);

            $this->response->code(200);
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
     * fetch single
     *
     * @return \Klein\Response
     */
    public function view()
    {
        $id = $this->request->param( 'id' );
        $model = QAModelTemplateDao::get([
            'id' => $id,
            'uid' => $this->getUser()->uid
        ]);

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

            $validatorObject = new \Validator\JSONValidatorObject();
            $validatorObject->json = $json;
            $validator = new \Validator\JSONValidator($this->getQaModelSchema());
            $validator->validate($validatorObject);

            $errors = $validator->getErrors();
            $code = ($validator->isValid()) ? 200 : 500;

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
        return file_get_contents( \INIT::$ROOT . '/inc/validation/schema/qa_model.json' );
    }
}