<?php

namespace Controller\API\Commons\Validators;

/**
 * @deprecated use Validators\ChunkPasswordValidator
 */

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\NotFoundException;
use Model\Jobs\ChunkDao;
use Model\Jobs\JobStruct;
use ReflectionException;

class JobPasswordValidator extends Base {
    /**
     * @var \Model\Jobs\JobStruct
     */
    private JobStruct $jStruct;

    /**
     * @throws ReflectionException
     * @throws \Model\Exceptions\NotFoundException
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
     * @return JobStruct
     */
    public function getJob(): JobStruct {
        return $this->jStruct;
    }

}
