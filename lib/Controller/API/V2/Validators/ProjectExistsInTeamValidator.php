<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 15/02/17
 * Time: 17.01
 *
 */

namespace API\V2\Validators;


use API\V2\Exceptions\NotFoundException;
use API\V2\KleinController;

class ProjectExistsInTeamValidator extends Base {

    public    $team;
    protected $controller;

    /**
     * @var \Projects_ProjectStruct
     */
    protected $project;

    public function __construct( KleinController $controller ) {
        $this->controller = $controller;
        parent::__construct( $controller->getRequest() );
    }

    public function _validate() {

        if( $this->request->id_team != $this->project->id_team ){
            throw new NotFoundException( "Project not found", 404 );
        }

    }

    /**
     * @param \Projects_ProjectStruct $project
     *
     * @return ProjectExistsInTeamValidator
     */
    public function setProject( \Projects_ProjectStruct $project ){
        $this->project = $project;
        return $this;
    }

}