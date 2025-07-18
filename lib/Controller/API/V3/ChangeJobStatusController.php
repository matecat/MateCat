<?php

namespace Controller\API\V3;

use Controller\API\V2\ChunkController as ChunkControllerV2;
use Utils\Constants\JobStatus;

/**
 * Class ChangeJobStatusController
 *
 * This class is responsible for handling requests to change the status of a job.
 * It extends the ChunkControllerV2 class.
 */
class ChangeJobStatusController extends ChunkControllerV2 {
    public function delete() {
        $this->return404IfTheJobWasDeleted();

        $this->changeStatus( JobStatus::STATUS_DELETED );
    }

    public function cancel() {
        $this->return404IfTheJobWasDeleted();

        $this->changeStatus( JobStatus::STATUS_CANCELLED );
    }

    public function archive() {
        $this->return404IfTheJobWasDeleted();

        $this->changeStatus( JobStatus::STATUS_ARCHIVED );
    }

    public function active() {
        $this->return404IfTheJobWasDeleted();

        $this->changeStatus( JobStatus::STATUS_ACTIVE );
    }
}
