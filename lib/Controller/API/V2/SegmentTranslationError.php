<?php
namespace API\V2  ;
use API\V2\Json\SegmentTranslationError as JsonFormatter;

class SegmentTranslationError extends ProtectedKleinController {

    private $chunk ;
    private $project ;
    private $validator ;
    private $segment ;
    private $translation ;

    protected function afterConstruct() {
        $this->validator = new JobPasswordValidator(
            $this->request->id_job,
            $this->request->password
        );
    }

    private function prepareData() {
        // Ensure chunk is in project
        $dao = new \Segments_SegmentDao( \Database::obtain() );

        $this->segment = $dao->getByChunkIdAndSegmentId(
            $this->request->id_job,
            $this->request->password,
            $this->request->id_segment
        );

        $this->chunk = \Chunks_ChunkDao::getByIdAndPassword(
            $this->request->id_job,
            $this->request->password
        );

        $this->project = \Projects_ProjectDao::findById(
            $this->chunk->id_project
        );

        $this->qa_model = \LQA\ModelDao::findById( $this->project->id_qa_model );
        $this->category = \LQA\CategoryDao::findById( $this->request->id_category );

        if ( $this->category->id_model != $this->qa_model->id ) {
            throw new Exceptions_RecordNotFound('QA model id mismatch');
        }
    }

    private function validate() {
        $this->prepareData() ;
        // TODO: extend validations here, for instance check the
        // project has a QA model.

        if (!$this->segment) {
            return false; // TODO: handle error
        }

        $this->translation = $this->segment->findTranslation( $this->request->id_job ) ;

        if (!$this->translation) {
            return false;  // TODO handle error
        }

        return true;
    }

    protected function validateRequest() {
        if ( !($this->validate() ) ) {
            $this->response->code(404);
            $this->response->json( array('error' => 'Not found') );
        }
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
            'start_position'      => $this->request->start_position,
            'stop_position'       => $this->request->stop_position,
            'is_full_segment'     => false,
            'penalty_points'      => $this->getPenaltyPoints(),
            'comment'             => $this->request->comment
        );

        \Log::doLog( $data );

        $result = \LQA\EntryDao::createEntry( $data );

        \Log::doLog( $result );
        // TODO pass the result to a json formatter

        $this->response->json( (array) $result );

    }

    private function getPenaltyPoints() {
        $severities = $this->category->getJsonSeverities() ;
        foreach($severities as $severity) {
            if ( $severity['label'] == $this->request->severity ) {
                return $severity['penalty'];
            }
        }
        throw new Exception('Provided severity was not found in model');
    }

}
