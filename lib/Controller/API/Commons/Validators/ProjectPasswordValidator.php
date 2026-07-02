<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/04/16
 * Time: 00:02
 */

namespace Controller\API\Commons\Validators;


use Controller\Abstracts\KleinController;
use Exception;
use Model\Exceptions\NotFoundException;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use ReflectionException;

class ProjectPasswordValidator extends Base
{
    /**
     * @var ?ProjectStruct
     */
    private ?ProjectStruct $project = null;

    private int $id_project;
    private string $password;

    public function __construct(KleinController $controller)
    {
        $filterArgs = [
            'id_project' => [
                'filter' => FILTER_SANITIZE_NUMBER_INT,
                ['filter' => FILTER_VALIDATE_INT]
            ],
            'password' => [
                'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            ],
        ];

        $postInput = (object)filter_var_array($controller->params, $filterArgs);

        $this->id_project = (int)$postInput->id_project;
        $this->password   = (string)$postInput->password;

        $controller->params['id_project'] = $this->id_project;
        $controller->params['password'] = $this->password;

        parent::__construct($controller);
    }

    /**
     * @return void
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws Exception
     */
    public function _validate(): void
    {
        if (!$this->password) {
            throw new NotFoundException("No project found.", 404);
        }

        $this->project = (new ProjectDao($this->controller->getDatabase()))->findByIdAndPassword(
            $this->id_project,
            $this->password
        );
    }

    /**
     * @return ?ProjectStruct
     */
    public function getProject(): ?ProjectStruct
    {
        return $this->project;
    }

    /**
     * @return int
     */
    public function getIdProject(): int
    {
        return $this->id_project;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

}