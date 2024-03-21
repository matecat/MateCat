<?php

namespace API\V3;

use API\V2\ChunkController as ChunkControllerV2;
use Constants_JobStatus;

/**
 * Class ChangeJobStatusController
 *
 * This class is responsible for handling requests to change the status of a job.
 * It extends the ChunkControllerV2 class.
 */
class ChangeJobStatusController extends ChunkControllerV2 {
    public function delete() {
        $this->return404IfTheJobWasDeleted();

        $this->changeStatus( Constants_JobStatus::STATUS_DELETED );
    }

    public function cancel() {
        $this->return404IfTheJobWasDeleted();

        $this->changeStatus( Constants_JobStatus::STATUS_CANCELLED );
    }

    public function archive() {
        $this->return404IfTheJobWasDeleted();

        $this->changeStatus( Constants_JobStatus::STATUS_ARCHIVED );
    }

    public function active() {
        $this->return404IfTheJobWasDeleted();

        $this->changeStatus( Constants_JobStatus::STATUS_ACTIVE );
    }
}
