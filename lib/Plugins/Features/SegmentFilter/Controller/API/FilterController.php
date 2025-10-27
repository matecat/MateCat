<?php


namespace Plugins\Features\SegmentFilter\Controller\API;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\ValidationError;
use Controller\API\Commons\Interfaces\ChunkPasswordValidatorInterface;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Exception;
use Model\Jobs\JobStruct;
use Plugins\Features\SegmentFilter\Model\FilterDefinition;
use Plugins\Features\SegmentFilter\Model\SegmentFilterModel;


class FilterController extends KleinController implements ChunkPasswordValidatorInterface {
    use ChunkNotFoundHandlerTrait;

    protected int    $id_job;
    protected string $jobPassword;

    /**
     * @param int $id_job
     *
     * @return $this
     */
    public function setIdJob( int $id_job ): static {
        $this->id_job = $id_job;

        return $this;
    }

    /**
     * @param string $jobPassword
     *
     * @return $this
     */
    public function setJobPassword( string $jobPassword ): static {
        $this->jobPassword = $jobPassword;

        return $this;
    }

    /**
     * @var ChunkPasswordValidator
     */
    protected ChunkPasswordValidator $validator;

    /**
     * @var FilterDefinition
     */
    private FilterDefinition $filter;

    /**
     * @param JobStruct $chunk
     *
     * @return $this
     */
    public function setChunk( JobStruct $chunk ): FilterController {
        $this->chunk = $chunk;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function index(): void {

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

    protected function afterConstruct(): void {
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