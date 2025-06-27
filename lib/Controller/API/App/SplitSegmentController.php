<?php

namespace Controller\API\App;

use CatUtils;
use Constants_TranslationStatus;
use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Database;
use Exception;
use InvalidArgumentException;
use Matecat\SubFiltering\MateCatFilter;
use Model\Exceptions\NotFoundException;
use Model\Jobs\ChunkDao;
use RuntimeException;
use TranslationsSplit_SplitDAO;
use TranslationsSplit_SplitStruct;

class SplitSegmentController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @throws Exception
     */
    public function split(): void {

        $request = $this->validateTheRequest();

        $translationStruct             = TranslationsSplit_SplitStruct::getStruct();
        $translationStruct->id_segment = $request[ 'id_segment' ];
        $translationStruct->id_job     = $request[ 'id_job' ];

        $featureSet = $this->getFeatureSet();

        /** @var MateCatFilter $Filter */
        $Filter = MateCatFilter::getInstance( $featureSet, $request[ 'jobStruct' ]->source, $request[ 'jobStruct' ]->target, [] );
        [ $request[ 'segment' ], $translationStruct->source_chunk_lengths ] = CatUtils::parseSegmentSplit( $request[ 'segment' ], '', $Filter );

        /* Fill the statuses with DEFAULT DRAFT VALUES */
        $pieces                                  = ( count( $translationStruct->source_chunk_lengths ) > 1 ? count( $translationStruct->source_chunk_lengths ) - 1 : 1 );
        $translationStruct->target_chunk_lengths = [
                'len'      => [ 0 ],
                'statuses' => array_fill( 0, $pieces, Constants_TranslationStatus::STATUS_DRAFT )
        ];

        $translationDao = new TranslationsSplit_SplitDAO( Database::obtain() );
        $result         = $translationDao->atomicUpdate( $translationStruct );

        if ( !$result ) {
            $this->log( "Failed while splitting/merging segment." );
            $this->log( $translationStruct );
            throw new RuntimeException( "Failed while splitting/merging segment." );
        }

        $this->response->json( [
                'data'   => 'OK',
                'errors' => [],
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
        $segment    = filter_var( $this->request->param( 'segment' ), FILTER_UNSAFE_RAW );
        $target     = filter_var( $this->request->param( 'target' ), FILTER_UNSAFE_RAW );

        if ( empty( $id_job ) ) {
            throw new InvalidArgumentException( "Missing id job", -3 );
        }

        if ( empty( $id_segment ) ) {
            throw new InvalidArgumentException( "Missing id segment", -4 );
        }

        if ( empty( $password ) ) {
            throw new InvalidArgumentException( "Missing job password", -5 );
        }

        // this checks that the json is valid, but not its content
        if ( is_null( $segment ) ) {
            throw new InvalidArgumentException( "Invalid source_chunk_lengths json", -6 );
        }

        // check Job password
        $jobStruct = ChunkDao::getByIdAndPassword( $id_job, $password );

        if ( is_null( $jobStruct ) ) {
            throw new NotFoundException( "Job not found" );
        }

        $this->featureSet->loadForProject( $jobStruct->getProject() );

        return [
                'id_job'     => $id_job,
                'id_segment' => $id_segment,
                'job_pass'   => $password,
                'segment'    => $segment,
                'target'     => $target,
        ];
    }
}
