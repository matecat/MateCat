<?php

namespace API\V3;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use Exception;
use Projects\ProjectTemplateDao;
use Validator\Errors\JSONValidatorError;

class ProjectTemplateController extends KleinController
{
    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * Get all entries
     */
    public function all()
    {
        $currentPage = (isset($_GET['page'])) ? $_GET['page'] : 1;
        $pagination = 20;
        $uid = $this->getUser()->uid;

        try {
            $this->response->status()->setCode( 200 );
            $this->response->json(ProjectTemplateDao::getAllPaginated($uid, $currentPage, $pagination));
            exit();

        } catch (Exception $exception){
            $code = ($exception->getCode() > 0) ? $exception->getCode() : 500;
            $this->response->status()->setCode( $code );
            $this->response->json([
                'error' => $exception->getMessage()
            ]);
            exit();
        }
    }

    /**
     * Get a single entry
     */
    public function get()
    {
        $id = filter_var( $this->request->id, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH|FILTER_FLAG_ENCODE_LOW );

        try {
            $this->response->status()->setCode( 200 );
            $this->response->json(ProjectTemplateDao::getById($id));
            exit();

        } catch (Exception $exception){
            $code = ($exception->getCode() > 0) ? $exception->getCode() : 500;
            $this->response->status()->setCode( $code );
            $this->response->json([
                'error' => $exception->getMessage()
            ]);
            exit();
        }
    }

    /**
     * Create new entry
     *
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
            $struct = ProjectTemplateDao::createFromJSON($json, $this->getUser()->uid);

            $this->response->code(201);

            return $this->response->json([
                'id' => (int)$struct->id,

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
     * Update an entry
     *
     * @return \Klein\Response
     */
    public function update()
    {
        // accept only JSON
        if($this->request->headers()->get('Content-Type') !== 'application/json'){
            $this->response->json([
                'message' => 'Method not allowed'
            ]);
            $this->response->code(405);
            exit();
        }

        $id = $this->request->param( 'id' );
        $model = ProjectTemplateDao::getById($id);

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
            $struct = ProjectTemplateDao::editFromJSON($model, $json, $this->getUser()->uid);

            $this->response->code(200);
            return $this->response->json([
                'id'      => (int)$struct->id,
                'version' => (int)$struct->version,
            ]);
        } catch (JSONValidatorError $exception){
            $errorCode = $exception->getCode() >= 400 ? $exception->getCode()  : 500;
            $this->response->code($errorCode);

            return $this->response->json($exception);
        } catch (\Exception $exception){
            $errorCode = $exception->getCode() >= 400 ? $exception->getCode()  : 500;
            $this->response->code($errorCode);

            return $this->response->json([
                'error' => $exception->getMessage()
            ]);
        }
    }

    /**
     * Delete an entry
     */
    public function delete()
    {
        $id = filter_var( $this->request->id, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH|FILTER_FLAG_ENCODE_LOW );

        try {
            ProjectTemplateDao::remove($id);

            $this->response->json([
                'id' => $id
            ]);
            exit();

        } catch (Exception $exception){
            $code = ($exception->getCode() > 0) ? $exception->getCode() : 500;
            $this->response->status()->setCode( $code );
            $this->response->json([
                'error' => $exception->getMessage()
            ]);
            exit();
        }
    }
}
