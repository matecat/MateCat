<?php

namespace API\V2  ;
use API\App\AbstractStatefulKleinController;
use API\V2\Json\SegmentTranslationIssue as JsonFormatter;
use Features\ReviewImproved;
use LQA\EntryDao as EntryDao ;
use Database;

class SegmentTranslationIssueController extends AbstractStatefulKleinController {

    /**
     * @var Validators\SegmentTranslationIssue
     */
    private $validator ;
    private $issue ;

    public function index() {
        $result = \LQA\EntryDao::findAllByTranslationVersion(
            $this->validator->translation->id_segment,
            $this->validator->translation->id_job,
            $this->getVersionNumber()
        );

        $json = new JsonFormatter( );
        $rendered = $json->render( $result );

        $this->response->json( array('issues' => $rendered) );
    }

    public function create() {

        $data = array(
            'id_segment'          => $this->request->id_segment,
            'id_job'              => $this->request->id_job,
            'id_category'         => $this->request->id_category,
            'severity'            => $this->request->severity,
            'translation_version' => $this->validator->translation->version_number,
            'target_text'         => $this->request->target_text,
            'start_node'          => $this->request->start_node,
            'start_offset'        => $this->request->start_offset,
            'end_node'            => $this->request->end_node,
            'end_offset'          => $this->request->end_offset,
            'is_full_segment'     => false,
            'comment'             => $this->request->comment,
            'uid'                 => $this->user->uid
        );

        $struct = new \LQA\EntryStruct( $data );

        $model = new ReviewImproved\TranslationIssueModel(
            $this->request->id_job,
            $this->request->password,
            $struct
        ) ;

        if ( $this->request->diff ) {
            $model->setDiff( $this->request->diff ) ;
        }

        $struct = $model->save();

        $categories = $this->validator->translation
                    ->getJob()->getProject()
                    ->getLqaModel()->getCategories();


        $json = new JsonFormatter( $categories );
        $rendered = $json->renderItem( $struct );

        $this->response->json( array('issue' => $rendered) );
    }

    public function update() {
        $issue = null;

        $postParams = $this->request->paramsPost() ;

        if ( $postParams['rebutted_at'] == null ) {
            $entryDao = new EntryDao( Database::obtain()->getConnection() );
            $issue = $entryDao->updateRebutted(
                $this->validator->issue->id, false
            );
        }

        $json = new JsonFormatter( $this->findCategories() );
        $rendered = $json->renderItem( $issue );

        $this->response->json( array('issue' => $rendered) );
    }

    public function delete() {

        $project = \Projects_ProjectDao::findByJobId($this->request->id_job);

        $this->featureSet->loadForProject($project);
        $codes = $this->featureSet->getCodes();

        if(in_array(\Features::REVIEW_EXTENDED, $codes)){
            $model = new \Features\ReviewExtended\TranslationIssueModel(
                    $this->request->id_job,
                    $this->validator->getChunkReview()->password,
                    $this->validator->issue
            );
        }
        else {
            $model = new ReviewImproved\TranslationIssueModel(
                    $this->request->id_job,
                    $this->validator->getChunkReview()->password,
                    $this->validator->issue
            );
        }



        $model->delete();

        $this->response->code(200);
    }

    protected function afterConstruct() {
        $this->validator = new Validators\SegmentTranslationIssue( $this->request );
        $this->appendValidator( $this->validator );
    }

    private function getVersionNumber() {
        if ( null !== $this->request->param('version_number') ) {
            return $this->request->param('version_number') ;
        }
        else {
            return $this->validator->translation->version_number ;
        }
    }

    private function findCategories() {
        $categories = $this->validator->translation
                ->getJob()->getProject()
                ->getLqaModel()->getCategories();

        return $categories ;
    }

}