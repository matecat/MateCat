<?php

namespace API\V2  ;
use API\V2\Json\SegmentTranslationIssue as JsonFormatter;
use Features\ReviewImproved;
use LQA\EntryDao as EntryDao ;

class SegmentTranslationIssueController extends ProtectedKleinController {

    private $chunk ;
    private $project ;
    /**
     * @var Validators\SegmentTranslationIssue
     */
    private $validator ;
    private $segment ;
    private $issue ;

    public function index() {
        $result = \LQA\EntryDao::findAllByTranslationVersion(
            $this->validator->translation->id_segment,
            $this->validator->translation->id_job,
            $this->getVersionNumber()
        );

        $json = new JsonFormatter( );
        $rendered = $json->renderArray( $result );

        $this->response->json( array('issues' => $rendered) );
    }

    public function create() {

        $data = array(
            'id_segment'          => $this->request->id_segment,
            'id_job'              => $this->request->id_job,
            'id_category'         => $this->request->id_category,
            'severity'            => $this->request->severity,
            'translation_version' => $this->validator->segment_translation->version_number,
            'target_text'         => $this->request->target_text,
            'start_node'          => $this->request->start_node,
            'start_offset'        => $this->request->start_offset,
            'end_node'            => $this->request->end_node,
            'end_offset'          => $this->request->end_offset,
            'is_full_segment'     => false,
            'comment'             => $this->request->comment
        );

        $struct = new \LQA\EntryStruct( $data );

        $model = new ReviewImproved\TranslationIssueModel(
            $this->request->id_job,
            $this->request->password,
            $struct
        ) ;

        $struct = $model->save();


        $json = new JsonFormatter();
        $rendered = $json->renderItem( $struct );

        $this->response->json( array('issue' => $rendered) );
    }


    public function delete() {
        $this->validateAdditionalPassword();

        $model = new ReviewImproved\TranslationIssueModel(
            $this->request->id_job,
            $this->request->password,
            $this->validator->issue
        );

        $model->delete();

        $this->response->code(200);
    }

    protected function afterConstruct() {
        $this->validator = new Validators\SegmentTranslationIssue( $this->request );
    }

    protected function validateRequest() {
        $this->validator->validate();
    }

    private function getVersionNumber() {
        if ( null !== $this->request->param('version_number') ) {
            return $this->request->param('version_number') ;
        }
        else {
            return $this->validator->translation->version_number ;
        }
    }

    private function validateAdditionalPassword() {
        // TODO: check password is good for deletion
    }

}
