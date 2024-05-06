<?php

namespace API\V3;

use API\V2\ProjectsController as ProjectsControllerV2;
use Constants_JobStatus;
use Exception;

class ChangeProjectStatusController extends ProjectsControllerV2
{
    /**
     * @throws Exception
     */
    public function cancel() {
        $this->changeStatus( Constants_JobStatus::STATUS_CANCELLED );
    }

    /**
     * @throws Exception
     */
    public function archive() {
        $this->changeStatus( Constants_JobStatus::STATUS_ARCHIVED );
    }

    /**
     * @throws Exception
     */
    public function delete() {
        $this->changeStatus( Constants_JobStatus::STATUS_DELETED );
    }

    /**
     * @throws Exception
     */
    public function active() {
        $this->changeStatus( Constants_JobStatus::STATUS_ACTIVE );
    }
}
