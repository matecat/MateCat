<?php


namespace Features\SegmentFilter\Controller\API;

use API\Commons\Exceptions\ValidationError;
use API\Commons\Validators\ChunkPasswordValidator;
use API\V2\BaseChunkController;
use Exception;
use Features\SegmentFilter\Model\FilterDefinition;
use Features\SegmentFilter\Model\SegmentFilterModel;
use Jobs_JobStruct;


class FilterController extends BaseChunkController {

    /**
     * @var ChunkPasswordValidator
     */
    protected ChunkPasswordValidator $validator;

    /**
     * @var FilterDefinition
     */
    private FilterDefinition $filter;

    /**
     * @param Jobs_JobStruct $chunk
     *
     * @return $this
     */
    public function setChunk( Jobs_JobStruct $chunk ): FilterController {
        $this->chunk = $chunk;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function index() {

        $this->return404IfTheJobWasDeleted();

        // TODO: validate the input filter
        $model = new SegmentFilterModel( $this->chunk, $this->filter );

        $ids_as_array = [];
        $ids_grouping = [];
        $segments_id  = $model->getSegmentList();
        foreach ( $segments_id as $segment_id ) {
            $ids_as_array[] = $segment_id[ 'id' ];
            if ( isset( $segment_id[ 'segment_hash' ] ) ) {
                $ids_grouping[ $segment_id[ 'segment_hash' ] ][] = $segment_id[ 'id' ];
            }
        }

        $this->response->json( [
                'segment_ids' => $ids_as_array,
                'count'       => count( $ids_as_array ),
                'grouping'    => $ids_grouping
        ] );

    }

    protected function afterConstruct() {
        $Validator = new ChunkPasswordValidator( $this );
        $Validator->onSuccess( function () use ( $Validator ) {
            $this->setChunk( $Validator->getChunk() );
            $get = $this->getRequest()->paramsGet();

            if ( !isset( $get[ 'filter' ] ) ) {
                throw new ValidationError( 'Filter is null. You must call this endpoint adding `filter[]` to query string. (Example: ?filter[status]=NEW)' );
            }

            $this->filter = new FilterDefinition( $get[ 'filter' ] );
            if ( !$this->filter->isValid() ) {
                throw new ValidationError( 'Filter is invalid' );
            }

            if ( $this->filter->isRevision() ) {
                $this->chunk->setIsReview( true );
            }

        } );
        $this->appendValidator( $Validator );
    }

}