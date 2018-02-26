<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/02/2017
 * Time: 10:57
 */

namespace API\V2\Validators;


use API\V2\KleinController;
use Exceptions\NotFoundError;

class TeamProjectValidator extends Base {


    /**
     * @var KleinController
     */
    protected $controller;

    /**
     * @var \Projects_ProjectStruct
     */
    protected $project;

    public function __construct( KleinController $controller ) {
        parent::__construct( $controller->getRequest() );
    }

    /**
     * @throws NotFoundError
     */
    public function _validate() {

        if ( empty( $this->project ) ) {
            throw new NotFoundError( "Not Found", 404 );
        }

    }

    /**
     * @param \Projects_ProjectStruct $project
     *
     * @return TeamProjectValidator
     */
    public function setProject( \Projects_ProjectStruct $project ){
        $this->project = $project;
        return $this;
    }

}