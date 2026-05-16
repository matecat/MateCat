<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 03/03/17
 * Time: 20.00
 *
 */

namespace Controller\API\V2;


use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Exception;
use Model\Exceptions\NotFoundException;
use Model\Projects\ProjectDao;
use Utils\ActiveMQ\ClientHelpers\ProjectQueue;
use View\API\V2\Json\CreationStatus;
use View\API\V2\Json\WaitCreation;

class ProjectCreationStatusController extends KleinController
{

    /**
     * @throws Exception
     */
    public function get(): void
    {
        $idProject = (int)$this->request->param('id_project');

        // validate id_project
        if (!is_numeric($this->request->param('id_project'))) {
            throw new Exception("ID project is not a valid integer", -1);
        }

        $result = ProjectQueue::getPublishedResults($idProject);

        if (empty($result)) {
            $this->_letsWait();
        } elseif (!empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                throw new Exception($error['message'], (int)$error['code']);
            }
        } else {
            // project is created, verify password authorization
            try {
                ProjectDao::findByIdAndPassword($idProject, $this->request->param('password'));
            } catch (NotFoundException) {
                throw new AuthorizationError('Not Authorized.');
            }

            if (empty($result['id_project'])) {
                $this->_letsWait();
            } else {
                $result = (object)$result;
                $this->response->json((new CreationStatus($result))->render());
            }
        }
    }


    protected function _letsWait(): void
    {
        $this->response->code(202);
        $this->response->json((new WaitCreation())->render());
    }
}
