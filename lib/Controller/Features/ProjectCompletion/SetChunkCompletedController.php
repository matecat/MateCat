<?php


use Features\ProjectCompletion\CompletionEventStruct;
use Features\ProjectCompletion\Model\EventModel;

class Features_ProjectCompletion_SetChunkCompletedController extends ajaxController {

    protected $id_job;
    protected $password;

    /**
     * @var Chunks_ChunkStruct
     */
    private $chunk;

    /**
     * @var array
     */
    private $__postInput = [];

    public function __construct() {

        parent::__construct();

        $filterArgs = [
                'id_job'           => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password'         => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'current_password' => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
        ];

        $this->__postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->id_job = $this->__postInput[ 'id_job' ];
        $this->password = $this->__postInput[ 'password' ];
        $this->received_password = $this->__postInput[ 'current_password' ];
    }

    public function doAction() {
        $this->chunk = Chunks_ChunkDao::getByIdAndPassword(
                $this->id_job,
                $this->password
        );

        if ( $this->chunk ) {
            $this->processInsert();
        } else {
            $this->result[ 'error' ] = 'record not found';
        }

    }

    private function processInsert() {
        $struct = new CompletionEventStruct( [
                'uid'               => $this->getUid(),
                'remote_ip_address' => Utils::getRealIpAddr(),
                'source'            => Chunks_ChunkCompletionEventStruct::SOURCE_USER,
                'is_review'         => $this->isRevision()
        ] );

        $model = new EventModel( $this->chunk, $struct );
        $model->save();

        $this->result[ 'data' ] = [
                'event' => [
                        'id' => (int)$model->getChunkCompletionEventId()
                ]
        ];
    }

    private function getUid() {
        $this->readLoginInfo();
        if ( $this->userIsLogged ) {
            return $this->user->uid;
        } else {
            return null;
        }
    }

}
