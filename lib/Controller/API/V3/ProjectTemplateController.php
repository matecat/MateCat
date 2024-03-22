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
        $pagination = (isset($_GET['perPage'])) ? $_GET['perPage'] : 20;

        if($pagination > 200){
            $pagination = 200;
        }

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
            $model = ProjectTemplateDao::getById($id);

            if(empty($model)){
                $this->response->code(404);

                return $this->response->json([
                    'error' => 'Model not found'
                ]);
            }

            if($model->uid !== $this->getUser()->uid){
                $this->response->code(403);

                return $this->response->json([
                    'error' => 'You are not authorized to see this model'
                ]);
            }

            $this->response->status()->setCode( 200 );
            $this->response->json($model);
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

            return $this->response->json($struct);

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
     * @throws Exception
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
        $uid = $this->getUser()->uid;

        // mark all templates as not default
        if($id == 0){
            ProjectTemplateDao::markAsNotDefault($uid, 0);

            return $this->response->json(ProjectTemplateDao::getDefaultTemplate($uid));
        }

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
            $struct = ProjectTemplateDao::editFromJSON($model, $json, $uid);

            $this->response->code(200);
            return $this->response->json($struct);
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
            $model = ProjectTemplateDao::getById($id);

            if(empty($model)){
                $this->response->code(404);

                return $this->response->json([
                    'error' => 'Model not found'
                ]);
            }

            ProjectTemplateDao::remove($id);

            $this->response->json([
                'id' => (int)$id
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

    /**
     * This is the Payable Rate Model JSON schema
     *
     * @return \Klein\Response
     */
    public function schema()
    {
        return $this->response->json(json_decode($this->getProjectTemplateModelSchema()));
    }

    /**
     * @return false|string
     */
    private function getProjectTemplateModelSchema()
    {
        return file_get_contents( \INIT::$ROOT . '/inc/validation/schema/project_template.json' );
    }
}
