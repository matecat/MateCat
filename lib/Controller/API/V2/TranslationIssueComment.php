<?php
namespace API\V2  ;
use API\V2\Json\TranslationIssueComment as JsonFormatter;
use LQA\EntryDao as EntryDao ;

class TranslationIssueComment extends ProtectedKleinController {

    private $validator ;

    public function create() {
        // $data = array(
        //     // 'uid'                 => null,
        //     'id_segment'          => $this->request->id_segment,
        //     'id_job'              => $this->request->id_job,
        //     'id_category'         => $this->request->id_category,
        //     'severity'            => $this->request->severity,
        //     'translation_version' => $this->translation->version_number,
        //     'target_text'         => $this->request->target_text,
        //     'start_node'          => $this->request->start_node,
        //     'start_offset'        => $this->request->start_offset,
        //     'end_node'            => $this->request->end_node,
        //     'end_offset'          => $this->request->end_offset,
        //     'is_full_segment'     => false,
        //     'penalty_points'      => $this->getPenaltyPoints(),
        //     'comment'             => $this->request->comment
        // );

        $result = \LQA\EntryCommentDao::createComment( $data );

        $json = new JsonFormatter( );
        $rendered = $json->renderItem( $result );

        $this->response->json( array('issue' => $rendered) );
    }

    protected function afterConstruct() {
        $this->validator = new Validators\SegmentTranslationIssue( $this->request );
    }

    protected function validateRequest() {
        $this->validator->validate();
    }

}
