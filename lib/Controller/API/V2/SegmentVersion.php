<?php
namespace API\V2  ;
use API\V2\Json\SegmentVersion as JsonFormatter;


class SegmentVersion extends ProtectedKleinController {

    private $job ;
    private $validator ;
    private $segment ;


    protected function afterConstruct() {
        $this->validator = new JobPasswordValidator(
            $this->request

        );
    }

    private function validate() {
        // JobPasswordValidator is actually useless
        // in this case since we need to check for the segment
        // scope inside the job.
        //
        // if ( !$this->validator->validate()  ) {
        //     return false;
        // }

        // Ensure chunk is in project
        $dao = new \Segments_SegmentDao( \Database::obtain() );

        $this->segment = $dao->getByChunkIdAndSegmentId(
            $this->request->id_job,
            $this->request->password,
            $this->request->id_segment
        );

        if (!$this->segment) {
            return false;
        }

        return true;
    }

    protected function validateRequest() {
        if ( !($this->validate() ) ) {
            $this->response->code(404);
            $this->response->json( array('error' => 'Not found') );
        }

        // validate the specified segment exists in job scope

    }

    public function index() {
        $results = \Translations_TranslationVersionDao::
            getVersionsForTranslation(
                $this->request->id_job,
                $this->request->id_segment
            );

        $formatted = new JsonFormatter( $results );

        $this->response->json( array(
            'versions' => $formatted->render()
        )) ;

    }

}
