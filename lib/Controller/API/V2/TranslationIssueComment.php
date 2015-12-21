<?php
namespace API\V2  ;
use API\V2\Json\TranslationIssueComment as JsonFormatter;
use LQA\EntryCommentDao ;

class TranslationIssueComment extends ProtectedKleinController {
    private $validator ;

    public function index() {
        $comments = EntryCommentDao::findByIssueId(
            $this->validator->issue->id
        );

        $json = new JsonFormatter( );
        $rendered = $json->renderArray( $comments );
        $this->response->json( array('comments' => $rendered ));
    }

    public function create() {
        $data = array(
            'comment' => $this->request->message,
            'id_qa_entry' => $this->validator->issue->id,
        );

        $result = EntryCommentDao::createComment( $data );

        $json = new JsonFormatter( );
        $rendered = $json->renderItem( $result );

        $this->response->json( array('comment' => $rendered) );
    }

    protected function afterConstruct() {
        $this->validator = new Validators\SegmentTranslationIssue( $this->request );
    }

    protected function validateRequest() {
        $this->validator->validate();
    }

}
