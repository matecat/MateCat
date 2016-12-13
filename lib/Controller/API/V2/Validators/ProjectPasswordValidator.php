<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/04/16
 * Time: 00:02
 */

namespace API\V2\Validators;


use Klein\Request;

class ProjectPasswordValidator extends Base {
    /**
     * @var \Projects_ProjectStruct
     */
    private $project;

    private $id_project;
    private $password;

    public function __construct( Request $request ) {

        $filterArgs = array(
                'id_project' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW
                ),
                'password'   => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
        );

        $postInput = (object)filter_var_array( $request->params(
                array(
                        'id_project',
                        'password',
                )
        ), $filterArgs );

        $this->id_project = $postInput->id_project;
        $this->password   = $postInput->password;

        $request->id_project = $this->id_project;
        $request->password   = $this->password;

        parent::__construct( $request );
    }

    public function validate() {
        $this->project = \Projects_ProjectDao::findByIdAndPassword(
                $this->id_project,
                $this->password
        );

        if ( !$this->project ) {
            throw new \Exceptions_RecordNotFound();
        }

    }

    /**
     * @return \Projects_ProjectStruct
     */
    public function getProject() {
        return $this->project;
    }

    /**
     * @return mixed
     */
    public function getIdProject() {
        return $this->id_project;
    }

    /**
     * @return mixed
     */
    public function getPassword() {
        return $this->password;
    }

}