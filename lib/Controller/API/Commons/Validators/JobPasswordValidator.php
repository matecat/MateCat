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
     * @var JobStruct
     */
    private JobStruct $jStruct;

    /**
     * @throws ReflectionException
     * @throws \Model\Exceptions\NotFoundException
     */
    public function __construct( KleinController $controller, ?bool $setChunkInController = true ) {

        parent::__construct( $controller );

        $filterArgs = [
                'id_job'   => [
                        'filter' => FILTER_SANITIZE_NUMBER_INT, [ 'filter' => FILTER_VALIDATE_INT ]
                ],
                'password' => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
        ];

        $postInput = (object)filter_var_array( $controller->params, $filterArgs );

        $this->jStruct = ChunkDao::getByIdAndPassword( $postInput->id_job, $postInput->password );

        $controller->params[ 'id_job' ]   = $postInput->id_job;
        $controller->params[ 'password' ] = $postInput->password;

        if ( $setChunkInController ) {
            $this->controller->setChunk( $this->jStruct ); //WIP remove this and use onSuccess and afterConstruct Methods in validators and controllers
        }

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
