<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/02/2017
 * Time: 10:57
 */

namespace Controller\API\Commons\Validators;


use Model\Exceptions\NotFoundException;
use Model\Projects\ProjectStruct;

class TeamProjectValidator extends Base
{

    /**
     * @var ProjectStruct
     */
    protected ProjectStruct $project;

    /**
     * @throws NotFoundException
     */
    public function _validate(): void
    {
        if (empty($this->project) || empty($this->project->id)) {
            throw new NotFoundException("Not Found", 404);
        }
    }

    /**
     * @param ProjectStruct $project
     *
     * @return TeamProjectValidator
     */
    public function setProject(ProjectStruct $project): TeamProjectValidator
    {
        $this->project = $project;

        return $this;
    }

}