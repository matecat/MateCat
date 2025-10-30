<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/04/16
 * Time: 00:02
 */

namespace Controller\API\Commons\Validators;


use Controller\Abstracts\KleinController;
use Model\Exceptions\NotFoundException;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use ReflectionException;

/**
 * This Validator is meant to provide access to a project with a password.
 * This validator is used to validate the project access token
 * It will return a ProjectStruct if the validation is successful
 */
class ProjectAccessTokenValidator extends Base {
    /**
     * @var ?ProjectStruct
     */
    private ?ProjectStruct $project = null;

    private int    $id_project;
    private string $accessToken;

    public function __construct( KleinController $controller ) {

        $filterArgs = [
                'id_project'  => [
                        'filter' => FILTER_SANITIZE_NUMBER_INT, [ 'filter' => FILTER_VALIDATE_INT ]
                ],
                'project_access_token' => [
                        'filter' => FILTER_SANITIZE_SPECIAL_CHARS, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
        ];

        $postInput = (object)filter_var_array( $controller->params, $filterArgs );

        $this->id_project  = $postInput->id_project;
        $this->accessToken = $postInput->project_access_token;

        $controller->params[ 'id_project' ]  = $this->id_project;

        parent::__construct( $controller );
    }

    /**
     * @return void
     * @throws ReflectionException
     * @throws NotFoundException
     */
    public function _validate(): void {

        $this->project = ProjectDao::findById(
                $this->id_project
        );

        if ( empty( $this->project ) || sha1( $this->project->id . $this->project->password ) !== $this->accessToken ) {
            throw new NotFoundException( "Project not found or access token is invalid.", 404 );
        }

        $this->controller->params[ 'password' ] = $this->project->password;

    }

    /**
     * @return ?ProjectStruct
     */
    public function getProject(): ?ProjectStruct {
        return $this->project;
    }

    /**
     * @return int
     */
    public function getIdProject(): int {
        return $this->id_project;
    }

    /**
     * @return string
     */
    public function getAccessToken(): string {
        return $this->accessToken;
    }

}