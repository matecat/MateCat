<?php

namespace API\V3;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use PayableRates\CustomPayableRateDao;
use PayableRates\CustomPayableRateStruct;
use Validator\Errors\JSONValidatorError;

class PayableRateController extends KleinController
{
    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function index()
    {
        $currentPage = (isset($_GET['page'])) ? $_GET['page'] : 1;
        $pagination = 20;
        $uid = $this->getUser()->uid;

        return $this->response->json(CustomPayableRateDao::getAllPaginated($uid, $currentPage, $pagination));
    }

    /**
     * @return \Klein\Response
     */
    public function create()
    {
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
            $id = CustomPayableRateDao::createFromJSON($json, $this->getUser()->uid);

            $this->response->code(201);

            return $this->response->json([
                'id' => (int)$id
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
     * @return \Klein\Response
     */
    public function delete()
    {
        $id = $this->request->param( 'id' );
        $model = CustomPayableRateDao::getById($id);

        if(empty($model)){
            $this->response->code(404);

            return $this->response->json([
                'error' => 'Model not found'
            ]);
        }

        if($this->getUser()->uid !== $model->uid){
            $this->response->code(401);

            return $this->response->json([
                'error' => 'User not allowed'
            ]);
        }

        try {
            CustomPayableRateDao::remove($id);

            return $this->response->json([
                'id' => (int)$id
            ]);
        } catch (\Exception $exception){
            $this->response->code(500);

            return $this->response->json([
                'error' => $exception->getMessage()
            ]);
        }
    }

    /**
     * @return \Klein\Response
     */
    public function edit()
    {
        $id = $this->request->param( 'id' );
        $model = CustomPayableRateDao::getById($id);

        if(empty($model)){
            $this->response->code(404);

            return $this->response->json([
                'error' => 'Model not found'
            ]);
        }

        if($this->getUser()->uid !== $model->uid){
            $this->response->code(401);

            return $this->response->json([
                'error' => 'User not allowed'
            ]);
        }

        try {
            $json = $this->request->body();
            $id = CustomPayableRateDao::editFromJSON($model, $json);

            $this->response->code(200);
            return $this->response->json([
                'id' => (int)$id
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
     * @return \Klein\Response
     */
    public function view()
    {
        $id = $this->request->param( 'id' );
        $model = CustomPayableRateDao::getById($id);

        if(empty($model)){
            $this->response->code(404);

            return $this->response->json([
                'error' => 'Model not found'
            ]);
        }

        if($this->getUser()->uid !== $model->uid){
            $this->response->code(401);

            return $this->response->json([
                'error' => 'User not allowed'
            ]);
        }

        return $this->response->json($model);
    }

    /**
     * This is the Payable Rate Model JSON schema
     *
     * @return \Klein\Response
     */
    public function schema()
    {
        return $this->response->json(json_decode($this->getPayableRateModelSchema()));
    }

    /**
     * Validate a Payable Rate Model template
     *
     * @return \Klein\Response
     */
    public function validate()
    {
        try {
            $json = $this->request->body();

            $validatorObject = new \Validator\JSONValidatorObject();
            $validatorObject->json = $json;
            $validator = new \Validator\JSONValidator($this->getPayableRateModelSchema());
            $validator->validate($validatorObject);

            $errors = $validator->getErrors();

            if($validator->isValid()){
                $customPayableRateStruct = new CustomPayableRateStruct();
                $customPayableRateStruct->hydrateFromJSON($json);
            }

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
    private function getPayableRateModelSchema()
    {
        return file_get_contents( \INIT::$ROOT . '/inc/validation/schema/payable_rate.json' );
    }
}