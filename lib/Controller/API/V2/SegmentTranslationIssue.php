<?php
namespace API\V2  ;
use API\V2\Json\SegmentTranslationIssue as JsonFormatter;
use LQA\EntryDao as EntryDao ;

class SegmentTranslationIssue extends ProtectedKleinController {

    private $chunk ;
    private $project ;
    private $validator ;
    private $segment ;
    private $translation ;
    private $issue ;

    public function index() {
        \Log::doLog("version number: ". $this->getVersionNumber());
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
            // 'uid'                 => null,
            'id_segment'          => $this->request->id_segment,
            'id_job'              => $this->request->id_job,
            'id_category'         => $this->request->id_category,
            'severity'            => $this->request->severity,
            'translation_version' => $this->translation->version_number,
            'target_text'         => $this->request->target_text,
            'start_node'          => $this->request->start_node,
            'start_offset'        => $this->request->start_offset,
            'end_node'            => $this->request->end_node,
            'end_offset'          => $this->request->end_offset,
            'is_full_segment'     => false,
            'penalty_points'      => $this->getPenaltyPoints(),
            'comment'             => $this->request->comment
        );

        $result = \LQA\EntryDao::createEntry( $data );
        $json = new JsonFormatter( );
        $rendered = $json->renderItem( $result );

        $this->response->json( array('issue' => $rendered) );
    }

    public function delete() {
        $this->validateAdditionalPassword();
        EntryDao::deleteEntry( $this->validator->issue );
        $this->response->code(200);
    }

    protected function afterConstruct() {
        $this->validator = new Validators\SegmentTranslationIssue( $this->request );
    }

    protected function validateRequest() {
        $this->validator->validate();
    }

    private function getVersionNumber() {
        \Log::doLog($this->request->params());

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

    private function getPenaltyPoints() {
        $severities = $this->validator->category->getJsonSeverities() ;
        foreach($severities as $severity) {
            if ( $severity['label'] == $this->request->severity ) {
                return $severity['penalty'];
            }
        }
        throw new ValidationError('Provided severity was not found in model');
    }

}
