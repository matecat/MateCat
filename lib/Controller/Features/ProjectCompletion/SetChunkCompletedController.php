<?php


use Features\ProjectCompletion\Model\EventModel ;

class Features_ProjectCompletion_SetChunkCompletedController extends ajaxController {

    private $id_job;
    private $password ;

    /**
     * @var \LQA\ChunkReviewStruct ;
     */
    private $chunk ;

    public function __construct() {
      $filterArgs = array(
            'id_job'      => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'password'    => array(
                'filter' => FILTER_SANITIZE_STRING,
                'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            ),
        );

        $this->__postInput = filter_input_array( INPUT_POST, $filterArgs );
    }

    public function doAction() {
        $this->chunk = Chunks_ChunkDao::getByIdAndPassword(
            $this->__postInput['id_job'],
            $this->__postInput['password']
        );

        if ( $this->chunk ) {
            $this->processInsert();
        }
        else {
            $this->result['error'] = 'record not found';
        }

    }

    private function processInsert() {

        $params = array(
            'uid' => $this->getUid(),
            'remote_ip_address' => Utils::getRealIpAddr(),
            'source' => Chunks_ChunkCompletionEventStruct::SOURCE_USER,
            'is_review' => $this->isRevision()
        );

        $model = new EventModel( $this->chunk, $params ) ;
        $model->save();

        $this->result[ 'data' ] = $this->chunk->toArray() ;
    }

    private function getUid() {
        $this->checkLogin();
        if ( $this->userIsLogged ) {
            return $this->uid ;
        } else {
            return null;
        }
    }

}
