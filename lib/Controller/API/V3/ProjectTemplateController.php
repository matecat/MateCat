<?php

namespace API\V3;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use Exception;
use Projects\ProjectTemplateDao;

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

    public function create()
    {
    }

    public function update()
    {
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
