<?php

namespace API\V3;

use API\V2\ProjectsController as ProjectsControllerV2;

class ChangeProjectStatusController extends ProjectsControllerV2
{
    public function cancel() {
        return $this->changeStatus(\Constants_JobStatus::STATUS_CANCELLED );
    }

    public function archive() {
        return $this->changeStatus(\Constants_JobStatus::STATUS_ARCHIVED );
    }

    public function delete() {
        return $this->changeStatus(\Constants_JobStatus::STATUS_DELETED );
    }

    public function active() {
        return $this->changeStatus(\Constants_JobStatus::STATUS_ACTIVE );
    }
}
