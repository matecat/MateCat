<?php


namespace Features\SegmentFilter\Controller\API;

use API\V2\KleinController;
use API\V2\Validators\ChunkPasswordValidator;
use API\V2\Exceptions\ValidationError;
use Chunks_ChunkStruct;
use Features\SegmentFilter\Model\SegmentFilterModel;

use Features\SegmentFilter\Model\FilterDefinition ;


class FilterController extends KleinController {

    /**
     * @var ChunkPasswordValidator
     */
    protected $validator;

    private $model ;

    /**
     * @var Chunks_ChunkStruct
     */
    private $chunk ;

    /**
     * @var FilterDefinition
     */
    private $filter ;
    /**
     * @param Chunks_ChunkStruct $chunk
     *
     * @return $this
     */
    public function setChunk( $chunk ) {
        $this->chunk = $chunk;

        return $this;
    }

    public function index() {
       // TODO: validate the input filter

        $this->model = new SegmentFilterModel( $this->chunk, $this->filter );

        // TODO: move this into a formatter
        $ids_as_array = array_map(function( array $record ) {
            return $record['id'];
        }, $this->model->getSegmentIds());

        $this->response->json( array(
            'segment_ids' => $ids_as_array,
            'count' => count($ids_as_array)
        ));
    }

    protected function afterConstruct() {
        $Validator = new ChunkPasswordValidator( $this ) ;
        $Controller = $this;
        $Validator->onSuccess( function () use ( $Validator, $Controller ) {
            $Controller->setChunk( $Validator->getChunk() );
            $get = $Controller->getRequest()->paramsGet();
            $filter = new FilterDefinition( $get['filter'] );
            if (! $filter->isValid() ) {
                throw new ValidationError('Filter is invalid');
            }
        } );
        $this->appendValidator( $Validator );
    }

}