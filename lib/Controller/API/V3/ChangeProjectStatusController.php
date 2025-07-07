<?php

namespace Controller\API\V3;

use Controller\API\V2\ProjectsController as ProjectsControllerV2;
use Exception;
use Utils\Constants\JobStatus;

class ChangeProjectStatusController extends ProjectsControllerV2 {
    /**
     * @throws Exception
     */
    public function cancel() {
        $this->changeStatus( JobStatus::STATUS_CANCELLED );
    }

    /**
     * @throws Exception
     */
    public function archive() {
        $this->changeStatus( JobStatus::STATUS_ARCHIVED );
    }

    /**
     * @throws Exception
     */
    public function delete() {
        $this->changeStatus( JobStatus::STATUS_DELETED );
    }

    /**
     * @throws Exception
     */
    public function active() {
        $this->changeStatus( JobStatus::STATUS_ACTIVE );
    }
}
