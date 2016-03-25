<?php


namespace Features\SegmentFilter\Controller\API;

use API\V2\JobPasswordValidator;
use API\V2\ValidationError;
use Features\SegmentFilter\Model\SegmentFilterModel;

use Features\SegmentFilter\Model\FilterDefinition ;


class FilterController extends \API\V2\ProtectedKleinController {

    /**
     * @var JobPasswordValidator
     */
    protected $validator;

    private $model ;

    /**
     * @var \Chunks_ChunkStruct
     */
    private $chunk ;

    /**
     * @var FilterDefinition
     */
    private $filter ;

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
        $this->validator = new JobPasswordValidator( $this->request );
    }

    /**
     * @throws ValidationError
     */
    protected function validateRequest() {
        $this->validator->validate();

        $this->chunk = $this->validator->getChunk();
        $get = $this->request->paramsGet();
        $this->filter = new FilterDefinition( $get['filter'] );

        if (! $this->filter->isValid() ) {
            throw new ValidationError('Filter is invalid');
        }

    }

}