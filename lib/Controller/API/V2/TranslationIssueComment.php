<?php
namespace API\V2  ;
use API\V2\Json\TranslationIssueComment as JsonFormatter;
use LQA\EntryDao as EntryDao ;

class TranslationIssueComment extends ProtectedKleinController {
    private $validator ;

    public function create() {
        $data = array(
            'comment' => $this->request->message,
            'id_qa_entry' => $this->validator->issue->id,
        );

        $result = \LQA\EntryCommentDao::createComment( $data );

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
