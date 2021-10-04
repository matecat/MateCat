<?php

namespace API\V2\Validators;

/**
 * @deprecated use Validators\ChunkPasswordValidator
 */

use API\V2\Exceptions\NotFoundException;
use API\V2\KleinController;
use Jobs_JobDao;
use Jobs_JobStruct;

class JobPasswordValidator extends Base {
    /**
     * @var \Jobs_JobStruct
     */
    private $jStruct;

    /**
     * @var KleinController
     */
    protected $controller;


    public function __construct( KleinController $controller ) {

        parent::__construct( $controller->getRequest() );
        $this->controller = $controller;

        $this->jStruct           = new Jobs_JobStruct();
        $this->jStruct->id       = $this->controller->params[ 'id_job' ];
        $this->jStruct->password = $this->controller->params[ 'password' ];
        $this->jStruct           = ( new Jobs_JobDao() )->setCacheTTL( 60 * 60 * 24 )->read( $this->jStruct )[ 0 ];

        $this->controller->setChunk( $this->jStruct );

    }

    /**
     * @return mixed|void
     * @throws NotFoundException
     */
    protected function _validate() {

        if ( empty( $this->jStruct ) ) {
            throw new NotFoundException( "Not Found.", 404 );
        }

    }

    /**
     * @return Jobs_JobStruct
     */
    public function getJob() {
        return $this->jStruct;
    }

}
