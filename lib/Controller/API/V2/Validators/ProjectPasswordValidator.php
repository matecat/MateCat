<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/04/16
 * Time: 00:02
 */

namespace API\V2\Validators;


use API\V2\KleinController;
use Exceptions\NotFoundException;
use Projects_ProjectDao;
use Projects_ProjectStruct;

class ProjectPasswordValidator extends Base {
    /**
     * @var Projects_ProjectStruct
     */
    private $project;

    private $id_project;
    private $password;

    public function __construct( KleinController $controller ) {

        $filterArgs = array(
                'id_project' => array(
                        'filter' => FILTER_SANITIZE_NUMBER_INT
                ),
                'password'   => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
        );

        $postInput = (object)filter_var_array( $controller->params, $filterArgs );

        $this->id_project = $postInput->id_project;
        $this->password   = $postInput->password;

        $controller->params[ 'id_project' ] = $this->id_project;
        $controller->params[ 'password' ]   = $this->password;

        parent::__construct( $controller->getRequest() );
    }

    /**
     * @return bool|mixed
     * @throws \Exceptions\NotFoundException
     */
    public function _validate() {

        $this->project = Projects_ProjectDao::findByIdAndPassword(
                $this->id_project,
                $this->password
        );

        if ( !$this->project ) {
            throw new NotFoundException();
        }

        return true;
    }

    /**
     * @return Projects_ProjectStruct
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