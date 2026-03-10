<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 15/02/17
 * Time: 17.01
 *
 */

namespace Controller\API\Commons\Validators;


use Controller\API\Commons\Exceptions\NotFoundException;
use Model\Projects\ProjectStruct;

class ProjectExistsInTeamValidator extends Base
{

    /**
     * @var ProjectStruct
     */
    protected ProjectStruct $project;

    public function _validate(): void
    {
        if ($this->request->param('id_team') != $this->project->id_team) {
            throw new NotFoundException("Project not found", 404);
        }
    }

    /**
     * @param ProjectStruct $project
     *
     * @return ProjectExistsInTeamValidator
     */
    public function setProject(ProjectStruct $project): ProjectExistsInTeamValidator
    {
        $this->project = $project;

        return $this;
    }

}