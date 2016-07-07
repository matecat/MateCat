<?php
namespace API\V2  ;
use API\V2\Json\TranslationIssueComment as JsonFormatter;
use LQA\EntryCommentDao ;
use LQA\EntryDao ;
use Database ;

class TranslationIssueComment extends ProtectedKleinController {
    /**
     * @var Validators\SegmentTranslationIssue
     */
    private $validator ;

    public function index() {
        $dao = new EntryCommentDao();

        $comments = $dao->findByIssueId(
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

        $dao = new EntryCommentDao();

        $result = $dao->createComment( $data );

        $json = new JsonFormatter( );
        $rendered = $json->renderItem( $result );

        $response = array('comment' => $rendered );

        $postParams = $this->request->paramsPost() ;

        if(  $postParams['rebutted'] === 'true' ) {
            $issue = $this->updateIssueWithRebutted();
            if ( $issue ) {
                $formatter = new  \API\V2\Json\SegmentTranslationIssue();
                $response['issue'] = $formatter->renderItem( $issue ) ;
            }
        }

        $this->response->json( $response );
    }

    /**
     * @return \LQA\EntryStruct
     */
    private function updateIssueWithRebutted() {
        $entryDao = new EntryDao( Database::obtain()->getConnection() );
        return $entryDao->updateRebutted(
                $this->validator->issue->id, true
        );
    }

    protected function afterConstruct() {
        $this->validator = new Validators\SegmentTranslationIssue( $this->request );
    }

    protected function validateRequest() {
        $this->validator->validate();
    }

}
