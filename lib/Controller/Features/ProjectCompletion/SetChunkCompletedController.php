<?php


use Features\ProjectCompletion\CompletionEventStruct;
use Features\ProjectCompletion\Model\EventModel;

class Features_ProjectCompletion_SetChunkCompletedController extends ajaxController {

    private $id_job;
    private $password ;

    /**
     * @var Chunks_ChunkStruct
     */
    private $chunk ;

    public function __construct() {
      $filterArgs = array(
            'id_job'      => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'password'    => array(
                'filter' => FILTER_SANITIZE_STRING,
                'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            ),
            'current_password'    => array(
                    'filter' => FILTER_SANITIZE_STRING,
                    'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->id_job = $__postInput['id_job'];
        $this->password = $__postInput['password'];
        $this->received_password = $__postInput['current_password'];
    }

    public function doAction() {
        $this->chunk = Chunks_ChunkDao::getByIdAndPassword(
            $this->id_job,
            $this->password
        );

        if ( $this->chunk ) {
            $this->processInsert();
        }
        else {
            $this->result['error'] = 'record not found';
        }

    }

    private function processInsert() {
        $struct = new CompletionEventStruct([
            'uid'               => $this->getUid(),
            'remote_ip_address' => Utils::getRealIpAddr(),
            'source'            => Chunks_ChunkCompletionEventStruct::SOURCE_USER,
            'is_review'         => $this->isRevision()
        ]);

        $model = new EventModel( $this->chunk, $struct ) ;
        $model->save();

        $this->result[ 'data' ] = ['event' => [
                'id' => (int) $model->getChunkCompletionEventId()
            ]
        ];
    }

    private function getUid() {
        $this->readLoginInfo();
        if ( $this->userIsLogged ) {
            return $this->user->uid ;
        } else {
            return null;
        }
    }

}
