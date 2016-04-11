<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/04/16
 * Time: 00:02
 */

namespace API\V2\Validators;


use Klein\Request ;

class ProjectPasswordValidator extends Base {
    /**
     * @var \Projects_ProjectStruct
     */
    private $project ;

    /**
     * @var \Jobs_JobStruct
     */
    private $job ;

    private $id_project;
    private $id_job;
    private $password ;

    public function __construct( Request $request  ) {
        $this->id_job = $request->id_job ;
        $this->id_project = $request->id_project ;
        $this->password = $request->password ;

        parent::__construct( $request );
    }

    public function validate() {
        $this->project = \Projects_ProjectDao::findByIdAndPassword(
                $this->id_project,
                $this->password
        );

        $this->job = \Jobs_JobDao::getById( $this->id_job );

        if ( !$this->job || $this->job->id_project != $this->project->id ) {
            throw new \Exceptions_RecordNotFound('job not found');
        }
    }

    public function getProject() {
        return $this->project ;
    }

    public function getJob() {
        return $this->job ; 
    }
}