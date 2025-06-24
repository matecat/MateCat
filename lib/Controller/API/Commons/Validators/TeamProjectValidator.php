<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/02/2017
 * Time: 10:57
 */

namespace Controller\API\Commons\Validators;


use Exceptions\NotFoundException;
use Projects_ProjectStruct;

class TeamProjectValidator extends Base {

    /**
     * @var Projects_ProjectStruct
     */
    protected Projects_ProjectStruct $project;

    /**
     * @throws NotFoundException
     */
    public function _validate(): void {

        if ( empty( $this->project ) || empty( $this->project->id ) ) {
            throw new NotFoundException( "Not Found", 404 );
        }

    }

    /**
     * @param Projects_ProjectStruct $project
     *
     * @return TeamProjectValidator
     */
    public function setProject( Projects_ProjectStruct $project ): TeamProjectValidator {
        $this->project = $project;

        return $this;
    }

}