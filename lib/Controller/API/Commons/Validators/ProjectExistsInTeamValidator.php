<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 15/02/17
 * Time: 17.01
 *
 */

namespace API\Commons\Validators;


use API\Commons\Exceptions\NotFoundException;
use Projects_ProjectStruct;

class ProjectExistsInTeamValidator extends Base {

    /**
     * @var Projects_ProjectStruct
     */
    protected Projects_ProjectStruct $project;

    public function _validate(): void {

        if ( $this->request->param( 'id_team' ) != $this->project->id_team ) {
            throw new NotFoundException( "Project not found", 404 );
        }

    }

    /**
     * @param Projects_ProjectStruct $project
     *
     * @return ProjectExistsInTeamValidator
     */
    public function setProject( Projects_ProjectStruct $project ): ProjectExistsInTeamValidator {
        $this->project = $project;

        return $this;
    }

}