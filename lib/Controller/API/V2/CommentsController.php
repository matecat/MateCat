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

use Comments_CommentDao ;

class CommentsController extends ProtectedKleinController {

    /**
     *
     * Gets the full list of comments for the current job
     *
     * @var ChunkPasswordValidator
     */
    private $validator ;

    public function index() {
        $chunk = $this->validator->getChunk() ;
        $comments = Comments_CommentDao::getCommentsForChunk( $chunk, array(
            'from_id' => $this->request->param( 'from_id' )
        ));

        $formatted = new SegmentComment( $comments ) ;
        $this->response->json( array('comments' => $formatted->render() ) ) ;
    }

    protected function validateRequest() {
        $this->validator->validate();
    }

    protected function afterConstruct() {
        $this->validator = new Validators\ChunkPasswordValidator( $this->request );
    }


}