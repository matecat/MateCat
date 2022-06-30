<?php

namespace API\V3;

use API\V2\ChunkController as ChunkControllerV2;

class ChangeJobStatusController extends ChunkControllerV2
{
    public function delete() {
        $this->return404IfTheJobWasDeleted();

        return $this->changeStatus( \Constants_JobStatus::STATUS_DELETED );
    }

    public function cancel() {
        $this->return404IfTheJobWasDeleted();

        return $this->changeStatus( \Constants_JobStatus::STATUS_CANCELLED );
    }

    public function archive() {
        $this->return404IfTheJobWasDeleted();

        return $this->changeStatus( \Constants_JobStatus::STATUS_ARCHIVED );
    }

    public function active() {
        $this->return404IfTheJobWasDeleted();

        return $this->changeStatus( \Constants_JobStatus::STATUS_ACTIVE );
    }
}
