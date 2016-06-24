<?php
namespace API\V2  ;
use API\V2\Json\TranslationIssueComment as JsonFormatter;
use LQA\EntryCommentDao ;
use LQA\EntryDao ;
use Database ;

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
        \Bootstrap::sessionStart();
        $uid = $_SESSION['uid'];

        $data = array(
            'comment' => $this->request->message,
            'id_qa_entry' => $this->validator->issue->id,
            'source_page' => $this->request->source_page,
            'uid' => $uid
        );

        $result = EntryCommentDao::createComment( $data );

        $json = new JsonFormatter( );
        $rendered = $json->renderItem( $result );

        $rebutted_entry = null;

        if( $this->request->rebutted === 'true' ) {
            $entryDao = new EntryDao( Database::obtain()->getConnection() );
            $rebutted_entry = $entryDao->updateRebutted(
                $this->validator->issue->id, true
            );
        }

        $this->response->json( array('comment' => $rendered, 'rebutted_entry' => $rebutted_entry) );
    }

    protected function afterConstruct() {
        $this->validator = new Validators\SegmentTranslationIssue( $this->request );
    }

    protected function validateRequest() {
        $this->validator->validate();
    }

}
