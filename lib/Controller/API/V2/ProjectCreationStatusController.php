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
use Controller\API\Commons\Exceptions\NotFoundException;
use Exception;
use Klein\Exceptions\LockedResponseException;
use Klein\Exceptions\ResponseAlreadySentException;
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
        if (!is_numeric($this->request->param('id_project'))) {
            throw new Exception("ID project is not a valid integer", -1);
        }

        $result = ProjectQueue::getPublishedResults((int)$this->request->param('id_project'));

        // The payload carries the project password from the enqueue on, so a pending, failed or created
        // project is checked the same way. Unknown, expired and wrong password get the same answer.
        // No database check: a failed creation deletes the project row.
        $published = $result['ppassword'] ?? null;
        if (!is_string($published) || !hash_equals($published, (string)$this->request->param('password'))) {
            throw new NotFoundException('Project not found.');
        }

        // A failed creation records exactly one error.
        if (!empty($result['errors'])) {
            throw new Exception($result['errors'][0]['message'], (int)$result['errors'][0]['code']);
        }

        // The worker replaces the pending payload with the creation result.
        if (!empty($result['pending'])) {
            $this->_letsWait();
        } else {
            $this->response->json((new CreationStatus((object)$result))->render());
        }
    }


    /**
     * @throws LockedResponseException
     * @throws ResponseAlreadySentException
     */
    protected function _letsWait(): void
    {
        $this->response->code(202);
        $this->response->json((new WaitCreation())->render());
    }
}
