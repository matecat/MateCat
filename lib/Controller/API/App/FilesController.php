<?php

namespace API\App;

use API\V2\Validators\ChunkPasswordValidator;
use Chunks_ChunkStruct;
use Files\FilesPartsDao;

class FilesController extends AbstractStatefulKleinController {

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk ;

    public function setChunk( Chunks_ChunkStruct $chunk ){
        $this->chunk = $chunk;
    }

    public function segments()
    {
        // `file_part_id` has the priority
        if(isset($_POST['file_part_id'])){
            $filePartId = $_POST['file_part_id'];
            $this->validateInteger($filePartId);
            $this->getFirstAndLastSegmentFromFilePartId($filePartId);
        }

        if(isset($_POST['file_id'])){
            $fileId = $_POST['file_id'];
            $this->validateInteger($fileId);
            $this->getFirstAndLastSegmentFromFileId($fileId);
        }

        $this->response->status()->setCode( 500 );
        $this->response->json( [
            'error' => 'Missing parameters. `file_part_id` or `file_id` must be provided'
        ] );
    }

    /**
     * @param $filePartId
     */
    private function getFirstAndLastSegmentFromFilePartId($filePartId)
    {
        $filePartsDao = new FilesPartsDao();
        $firstAndLastSegment = $filePartsDao->getFirstAndLastSegment($filePartId);

        if(null === $firstAndLastSegment->first_segment){
            $this->response->status()->setCode( 404 );
            $this->response->json( [
                'error' => 'File part id '. $filePartId .' was not found'
            ] );
            exit();
        }

        $this->response->json( [
            'first_segment' => (int)$firstAndLastSegment->first_segment,
            'last_segment' => (int)$firstAndLastSegment->last_segment,
        ] );
        exit();
    }

    /**
     * @param $fileId
     */
    private function getFirstAndLastSegmentFromFileId($fileId)
    {
        $fileInfo = \Jobs_JobDao::getFirstSegmentOfFilesInJob( $this->chunk, 60 * 5 );

        if(empty($fileInfo)){
            $this->response->status()->setCode( 404 );
            $this->response->json( [
                'error' => 'File id '. $fileId .' was not found'
            ] );
            exit();
        }

        $firstAndLastSegment = array_filter($fileInfo,function ($item) use ($fileId) {
            return $item->id_file == $fileId;
        })[0];

        $this->response->json( [
            'fist_segment' => (int)$firstAndLastSegment->first_segment,
            'last_segment' => (int)$firstAndLastSegment->last_segment,
        ] );
        exit();
    }

    /**
     * @param $value
     */
    private function validateInteger($value)
    {
        if (!filter_var($value, FILTER_VALIDATE_INT)) {

            $this->response->status()->setCode( 500 );
            $this->response->json( [
                'error' => '`file_part_id` is not an integer'
            ] );
            exit();
        }
    }

    protected function afterConstruct() {
        $Validator = ( new ChunkPasswordValidator( $this ) );
        $Controller = $this;
        $Validator->onSuccess( function () use ( $Validator, $Controller ) {
            $Controller->setChunk( $Validator->getChunk() );
        } );

        $this->appendValidator( $Validator );
    }
}