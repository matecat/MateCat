<?php

namespace Controller\API\Commons\Validators;

/**
 * @deprecated use Validators\ChunkPasswordValidator
 */

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\NotFoundException;
use Jobs_JobStruct;
use Model\Jobs\ChunkDao;
use ReflectionException;

class JobPasswordValidator extends Base {
    /**
     * @var Jobs_JobStruct
     */
    private Jobs_JobStruct $jStruct;

    /**
     * @throws ReflectionException
     * @throws \Exceptions\NotFoundException
     */
    public function __construct( KleinController $controller ) {

        parent::__construct( $controller );
        $this->jStruct = ChunkDao::getByIdAndPassword( $this->controller->params[ 'id_job' ], $this->controller->params[ 'password' ] );

        $this->controller->setChunk( $this->jStruct );

    }

    /**
     * @return mixed|void
     * @throws NotFoundException
     */
    protected function _validate(): void {

        if ( empty( $this->jStruct ) ) {
            throw new NotFoundException( "Not Found.", 404 );
        }

    }

    /**
     * @return Jobs_JobStruct
     */
    public function getJob(): Jobs_JobStruct {
        return $this->jStruct;
    }

}
