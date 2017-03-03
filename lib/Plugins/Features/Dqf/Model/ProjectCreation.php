<?php



namespace Features\Dqf\Model ;

use Projects_ProjectStruct ;

class ProjectCreation {

    /**
     * @var Projects_ProjectStruct
     */
    protected $project ;

    protected $current_state ;

    public function __construct( Projects_ProjectStruct $project ) {

        $this->project = $project ;
    }


    public function process() {

        /**
         * 1. read status from database
         * 2. trigger corresponding method to start over where the process halted
         * 3.
         * 4.
         *
         *
         *
         */

    }

    protected function _createProject() {


    }

    protected function _submitProjectFiles() {


    }

    protected function _submitSourceSegments() {

    }

    protected function _submitTargetLanguages() {

    }

    public function submitChildProjects() {

    }

    public function submitChildProjectTargetLanguage() {

    }

    public function submitReviewSettings() {

    }


    protected function closeProjectCreation() {

    }



}