<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\JobPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Model\Exceptions\NotFoundException;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectDao;
use Model\Segments\SegmentDao;
use ReflectionException;
use View\API\V2\Json\SegmentTranslationMismatches;

class GetTranslationMismatchesController extends KleinController {
    private JobStruct $chunk;

    /**
     * @param JobStruct $chunk
     *
     * @return $this
     */
    public function setChunk( JobStruct $chunk ): GetTranslationMismatchesController {
        $this->chunk = $chunk;

        return $this;
    }

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     */
    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
        $this->appendValidator( new JobPasswordValidator( $this ) );
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function get(): void {

        $id_segment = filter_var( $this->request->param( 'id_segment' ), FILTER_SANITIZE_NUMBER_INT );

        $this->featureSet->loadForProject( ProjectDao::findByJobId( $this->params[ 'id_job' ], 60 * 60 ) );
        $parsedIdSegment = $this->parseIdSegment( $id_segment );

        if ( $parsedIdSegment[ 'id_segment' ] == '' ) {
            $parsedIdSegment[ 'id_segment' ] = 0;
        }

        $sDao                   = new SegmentDao();
        $Translation_mismatches = $sDao->setCacheTTL( 1 * 60 /* 1 minutes cache */ )->getTranslationsMismatches( $this->params[ 'id_job' ], $this->params[ 'password' ], $parsedIdSegment[ 'id_segment' ] );

        $this->response->json( [
                'errors' => [],
                'code'   => 1,
                'data'   => ( new SegmentTranslationMismatches( $Translation_mismatches, $this->chunk->id_project, count( $Translation_mismatches ), $this->featureSet ) )->render()
        ] );

    }

}