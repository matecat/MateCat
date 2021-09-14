<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 12/05/16
 * Time: 15:08
 */

namespace API\V2;


use API\V2\Json\SegmentComment;
use API\V2\Validators\ChunkPasswordValidator;
use Chunks_ChunkStruct;
use Comments_CommentDao;

class CommentsController extends BaseChunkController {

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk;

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

        $this->return404IfTheJobWasDeleted();

        $comments = Comments_CommentDao::getCommentsForChunk( $this->chunk, array(
            'from_id' => $this->request->param( 'from_id' )
        ));

        $formatted = new SegmentComment( $comments ) ;
        $this->response->json( array('comments' => $formatted->render() ) ) ;
    }

    protected function afterConstruct() {
        $Validator = new ChunkPasswordValidator( $this ) ;
        $Controller = $this;
        $Validator->onSuccess( function () use ( $Validator, $Controller ) {
            $Controller->setChunk( $Validator->getChunk() );
        } );
        $this->appendValidator( $Validator );
    }

}