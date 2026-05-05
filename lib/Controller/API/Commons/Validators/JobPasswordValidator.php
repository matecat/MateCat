<?php

namespace Controller\API\Commons\Validators;

/**
 * @deprecated use Validators\ChunkPasswordValidator
 */

use Controller\API\Commons\Exceptions\NotFoundException;
use Model\Jobs\ChunkDao;
use Model\Jobs\JobStruct;
use ReflectionException;

class JobPasswordValidator extends Base
{
    /**
     * @var JobStruct
     */
    private JobStruct $jStruct;

    /**
     * @return void
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws \Model\Exceptions\NotFoundException
     */
    protected function _validate(): void
    {
        $filterArgs = [
            'id_job' => [
                'filter' => FILTER_SANITIZE_NUMBER_INT,
                ['filter' => FILTER_VALIDATE_INT]
            ],
            'password' => [
                'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            ],
        ];

        $postInput = (object)filter_var_array($this->controller->params, $filterArgs);

        $this->jStruct = ChunkDao::getByIdAndPassword($postInput->id_job, $postInput->password);

        $this->controller->params['id_job'] = $postInput->id_job;
        $this->controller->params['password'] = $postInput->password;
    }

    /**
     * @return JobStruct
     */
    public function getJob(): JobStruct
    {
        return $this->jStruct;
    }

}
