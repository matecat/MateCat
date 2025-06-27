<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Model\Projects\ProjectDao;
use Model\Segments\SegmentDao;
use ReflectionException;
use View\API\V2\Json\SegmentTranslationMismatches;

class GetTranslationMismatchesController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function get(): void {

        $request = $this->validateTheRequest();

        $id_job     = $request[ 'id_job' ];
        $id_segment = $request[ 'id_segment' ];
        $password   = $request[ 'password' ];

        $this->featureSet->loadForProject( ProjectDao::findByJobId( $id_job, 60 * 60 ) );
        $parsedIdSegment = $this->parseIdSegment( $id_segment );

        if ( $parsedIdSegment[ 'id_segment' ] == '' ) {
            $parsedIdSegment[ 'id_segment' ] = 0;
        }

        $sDao                   = new SegmentDao();
        $Translation_mismatches = $sDao->setCacheTTL( 1 * 60 /* 1 minutes cache */ )->getTranslationsMismatches( $id_job, $password, $parsedIdSegment[ 'id_segment' ] );

        $this->response->json( [
                'errors' => [],
                'code'   => 1,
                'data'   => ( new SegmentTranslationMismatches( $Translation_mismatches, count( $Translation_mismatches ), $this->featureSet ) )->render()
        ] );

    }

    /**
     * @return array
     * @throws Exception
     */
    private function validateTheRequest(): array {
        $id_job     = filter_var( $this->request->param( 'id_job' ), FILTER_SANITIZE_NUMBER_INT );
        $id_segment = filter_var( $this->request->param( 'id_segment' ), FILTER_SANITIZE_NUMBER_INT );
        $password   = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );

        if ( empty( $id_job ) ) {
            throw new InvalidArgumentException( "No id job provided", -1 );
        }

        if ( empty( $id_segment ) ) {
            throw new InvalidArgumentException( "No id segment provided", -1 );
        }

        if ( empty( $password ) ) {
            throw new InvalidArgumentException( "No job password provided", -1 );
        }

        return [
                'id_job'     => $id_job,
                'id_segment' => $id_segment,
                'password'   => $password,
        ];
    }
}