<?php

namespace Controller\API\V2;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\API\Commons\Interfaces\ChunkPasswordValidatorInterface;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\SegmentTranslationIssueValidator;
use Exception;
use Model\DataAccess\Database;
use Model\Exceptions\ValidationError;
use Model\LQA\EntryCommentDao;
use Model\LQA\EntryDao as EntryDao;
use Model\LQA\EntryStruct;
use Plugins\Features\ReviewExtended\ReviewUtils;
use Plugins\Features\ReviewExtended\TranslationIssueModel;
use View\API\V2\Json\SegmentTranslationIssue as TranslationIssueFormatter;
use View\API\V2\Json\TranslationIssueComment;

class SegmentTranslationIssueController extends AbstractStatefulKleinController implements ChunkPasswordValidatorInterface {

    protected int    $id_job;
    protected string $jobPassword;

    /**
     * @param int $id_job
     *
     * @return $this
     */
    public function setIdJob( int $id_job ): static {
        $this->id_job = $id_job;

        return $this;
    }

    /**
     * @param string $jobPassword
     *
     * @return $this
     */
    public function setJobPassword( string $jobPassword ): static {
        $this->jobPassword = $jobPassword;

        return $this;
    }

    /**
     * @var SegmentTranslationIssueValidator
     */
    private SegmentTranslationIssueValidator $validator;

    public function index(): void {
        $result = EntryDao::findAllByTranslationVersion(
                $this->validator->translation->id_segment,
                $this->validator->translation->id_job,
                $this->getVersionNumber()
        );

        $json     = new TranslationIssueFormatter();
        $rendered = $json->render( $result );

        $this->response->json( [ 'issues' => $rendered ] );
    }

    /**
     * @throws ValidationError
     */
    public function create(): void {
        $data = [
                'id_segment'          => $this->request->param( 'id_segment' ),
                'id_job'              => $this->request->param( 'id_job' ),
                'id_category'         => $this->request->param( 'id_category' ),
                'severity'            => $this->request->param( 'severity' ),
                'translation_version' => $this->validator->translation->version_number,
                'target_text'         => $this->request->param( 'target_text' ),
                'start_node'          => $this->request->param( 'start_node' ),
                'start_offset'        => $this->request->param( 'start_offset' ),
                'end_node'            => $this->request->param( 'end_node' ),
                'end_offset'          => $this->request->param( 'end_offset' ),
                'is_full_segment'     => false,
                'comment'             => $this->request->param( 'comment' ),
                'uid'                 => $this->user->uid ?? null,
                'source_page'         => ReviewUtils::revisionNumberToSourcePage( $this->request->param( 'revision_number' ) ),
        ];

        Database::obtain()->begin();

        $struct = new EntryStruct( $data );

        $model = $this->_getSegmentTranslationIssueModel(
                $this->request->param( 'id_job' ),
                $this->request->param( 'password' ),
                $struct
        );

        if ( $this->request->param( 'diff' ) ) {
            $model->setDiff( (array)$this->request->param( 'diff' ) );
        }

        $struct = $model->save();

        Database::obtain()->commit();

        $json     = new TranslationIssueFormatter();
        $rendered = $json->renderItem( $struct );

        $this->response->json( [ 'issue' => $rendered ] );
    }

    /**
     * This method does nothing
     * @return void
     */
    public function update(): void {
        $this->response->json( [ 'issue' => 'ok' ] );
    }

    /**
     * @throws Exception
     */
    public function delete(): void {

        Database::obtain()->begin();
        $model = $this->_getSegmentTranslationIssueModel(
                $this->request->param( 'id_job' ),
                $this->request->param( 'password' ),
                $this->validator->issue
        );
        $model->delete();
        Database::obtain()->commit();

        $this->response->code( 200 );
    }

    public function getComments(): void {
        $dao = new EntryCommentDao();

        $comments = $dao->findByIssueId(
                $this->validator->issue->id
        );

        $json     = new TranslationIssueComment();
        $rendered = $json->render( $comments );
        $this->response->json( [ 'comments' => $rendered ] );
    }

    /**
     */
    public function createComment(): void {

        $data = [
                'comment'     => $this->request->param( 'message' ),
                'id_qa_entry' => $this->validator->issue->id,
                'source_page' => $this->request->param( 'source_page' ),
                'uid'         => $this->user->uid
        ];

        $dao   = new EntryCommentDao();
        $entry = EntryDao::findById( $this->validator->issue->id );

        $dao->createComment( $data );

        $json     = new TranslationIssueFormatter();
        $rendered = $json->renderItem( $entry );

        $response = [ 'comment' => $rendered ];

        $this->response->json( $response );

    }

    /**
     * @param int         $id_job
     * @param string      $password
     * @param EntryStruct $issue
     *
     * @return TranslationIssueModel
     */
    protected function _getSegmentTranslationIssueModel( int $id_job, string $password, EntryStruct $issue ): TranslationIssueModel {
        return new TranslationIssueModel( $id_job, $password, $issue );
    }

    protected function afterConstruct(): void {

        $jobValidator = new ChunkPasswordValidator( $this );
        $jobValidator->onSuccess( function () use ( $jobValidator ) {
            //enable dynamic loading (Factory) by callback hook on revision features
            $this->validator = ( new SegmentTranslationIssueValidator( $this ) )->setChunkReview( $jobValidator->getChunkReview() );
            $this->validator->validate();
        } );
        $this->appendValidator( $jobValidator );
        $this->appendValidator( new LoginValidator( $this ) );

    }

    private function getVersionNumber(): int {
        if ( null !== $this->request->param( 'version_number' ) ) {
            return (int)$this->request->param( 'version_number' );
        }

        return (int)$this->validator->translation->version_number;
    }

}