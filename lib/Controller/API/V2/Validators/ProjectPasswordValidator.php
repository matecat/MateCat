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

    private $id_project;
    private $password ;

    public function __construct( Request $request  ) {
        $this->id_project = $request->id_project ;
        $this->password = $request->password ;

        parent::__construct( $request );
    }

    public function validate() {
        $this->project = \Projects_ProjectDao::findByIdAndPassword(
                $this->id_project,
                $this->password
        );

        if (!$this->project) {
            throw new \Exceptions_RecordNotFound();
        }

    }

    /**
     * @return \Projects_ProjectStruct
     */
    public function getProject() {
        return $this->project ;
    }
}