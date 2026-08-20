<?php

namespace Controller\API\V2;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectAccessValidator;
use Controller\API\Commons\Validators\SegmentTranslationIssueValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Exception;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\LQA\EntryCommentDao;
use Model\Projects\ProjectDao;
use Model\LQA\EntryDao as EntryDao;
use Model\LQA\EntryStruct;
use Model\LQA\EntryValidator;
use Model\Translations\SegmentTranslationDao;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use Plugins\Features\ReviewExtended\TranslationIssueModel;
use Plugins\Features\TranslationVersions\Model\TranslationVersionDao;
use RuntimeException;
use TypeError;
use Utils\Constants\SourcePages;
use View\API\V2\Json\SegmentTranslationIssue as TranslationIssueFormatter;
use View\API\V2\Json\TranslationIssueComment;

class SegmentTranslationIssueController extends AbstractStatefulKleinController {

    use ChunkNotFoundHandlerTrait;

    /**
     * @var SegmentTranslationIssueValidator
     */
    private SegmentTranslationIssueValidator $validator;

    /**
     * The chunk review resolved from the password this request authenticated with.
     *
     * @var ChunkReviewStruct
     */
    private ChunkReviewStruct $chunkReview;

    /**
     * @throws RuntimeException
     */
    public function index(): void {
        $result = (new EntryDao($this->getDatabase()))->findAllByTranslationVersion(
            $this->validator->translation->id_segment,
            $this->validator->translation->id_job,
            $this->getVersionNumber()
        );

        $json = new TranslationIssueFormatter(new EntryCommentDao($this->getDatabase()));
        $rendered = $json->render( $result );

        $this->response->json( [ 'issues' => $rendered ] );
    }

    /**
     * @throws ValidationError
     * @throws RuntimeException
     * @throws AuthorizationError
     * @throws Exception
     * @throws \TypeError
     */
    public function create(): void {
        // Penalty points are charged to a review row, so filing an issue requires a review
        // credential. A translate password resolves to no phase the caller may score.
        if ( $this->credentialSourcePage() === SourcePages::SOURCE_PAGE_TRANSLATE ) {
            throw new AuthorizationError( 'A revision password is required to create an issue', 401 );
        }

        $this->validator->ensureSegmentRevisionMatchesCredentialPhase();

        $data = [
            'id_segment' => $this->request->param( 'id_segment' ),
            'id_job' => $this->request->param( 'id_job' ),
            'id_category' => $this->request->param( 'id_category' ),
            'severity' => $this->request->param( 'severity' ),
            'translation_version' => $this->validator->translation->version_number,
            'target_text' => $this->request->param( 'target_text' ),
            'start_node' => $this->request->param( 'start_node' ),
            'start_offset' => $this->request->param( 'start_offset' ),
            'end_node' => $this->request->param( 'end_node' ),
            'end_offset' => $this->request->param( 'end_offset' ),
            'is_full_segment' => false,
            'comment' => $this->request->param( 'comment' ),
            'uid' => $this->user->uid ?? null,
            'source_page' => $this->credentialSourcePage(),
        ];

        $this->getDatabase()->begin();

        $struct = new EntryStruct( $data );

        // The credential decides both the phase the issue belongs to and the review row its penalty
        // points are charged to, so the two can no longer disagree (GHSA-7q94-2fmr-3p42).
        $model = $this->_getSegmentTranslationIssueModel(
            $this->chunkReview->id_job ?? throw new RuntimeException( 'Missing job id' ),
            $this->chunkReview->review_password ?? throw new RuntimeException( 'Missing review password' ),
            $struct
        );

        $struct = $model->save();

        $this->getDatabase()->commit();

        $json     = new TranslationIssueFormatter(new EntryCommentDao($this->getDatabase()));
        $rendered = $json->renderItem( $struct );

        $this->response->json( [ 'issue' => $rendered ] );
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function update(): void {
        // An issue scores a review row, so editing one requires a review credential. A translate
        // password resolves to no phase the caller may score.
        if ( $this->credentialSourcePage() === SourcePages::SOURCE_PAGE_TRANSLATE ) {
            throw new AuthorizationError( 'A revision password is required to edit an issue', 401 );
        }

        $this->validator->ensureSegmentRevisionMatchesCredentialPhase();

        $data = [
                'id_issue'            => $this->request->param( 'id_issue' ),
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
        ];

        $this->getDatabase()->begin();

        $oldStruct = (new EntryDao($this->getDatabase()))->findById( $data[ 'id_issue' ] );

        if ( $oldStruct === null ) {
            throw new NotFoundException( "Issue not found", 404 );
        }

        $data['source_page'] = $oldStruct->source_page;

        $chunkReviewDao = new ChunkReviewDao($this->getDatabase());

        // Job and phase were already resolved from the presented credential by ChunkPasswordValidator.
        $jobStruct = $this->chunk;

        $this->checkLoggedUserPermissions($oldStruct, $jobStruct, $this->user);

        // This is the chunk review that will be updated
        $chunkReviewToBeUpdated = $chunkReviewDao->findByIdJobAndPasswordAndSourcePage(
            $jobStruct->id ?? throw new RuntimeException('Missing job id'),
            $jobStruct->password ?? throw new RuntimeException('Missing job password'),
            $oldStruct->source_page
        );

        if ( $chunkReviewToBeUpdated === null ) {
            throw new NotFoundException( "Job not found", 404 );
        }

        $oldStruct->setDefaults(
            new EntryValidator( $oldStruct, database: $this->getDatabase() ),
            new SegmentTranslationDao( $this->getDatabase() )
        );

        $newStruct     = new EntryStruct( $data );
        $newStruct->id = $data[ 'id_issue' ];
        $newStruct->setDefaults(
            new EntryValidator( $newStruct, database: $this->getDatabase() ),
            new SegmentTranslationDao( $this->getDatabase() )
        );

        // remove old issue
        $model = $this->_getSegmentTranslationIssueModel(
            $chunkReviewToBeUpdated->id_job,
            $chunkReviewToBeUpdated->review_password ?? throw new RuntimeException('Missing review password'),
            $oldStruct
        );

        $model->delete();

        // create new issue
        $model = $this->_getSegmentTranslationIssueModel(
            $chunkReviewToBeUpdated->id_job,
            $chunkReviewToBeUpdated->review_password ?? throw new RuntimeException('Missing review password'),
            $newStruct
        );

        $struct = $model->save();

        // move comments from old issue to new one
        $commentDao = new EntryCommentDao($this->getDatabase());
        $commentDao->move(
            (int)$oldStruct->id,
            (int)$struct->id
        );

         // update replies count
         $entryDao = new EntryDao($this->getDatabase());
         $entryDao->updateRepliesCount($struct->id ?? throw new RuntimeException('Missing entry id'));

        $this->getDatabase()->commit();

        $msg = "[AUDIT][ISSUE_UPDATE] issue_id={$struct->id}; segment_id={$struct->id_segment}; user={$this->user->email}; new_severity={$struct->severity}";
        $this->logger->debug($msg);

        $json = new TranslationIssueFormatter(new EntryCommentDao($this->getDatabase()));
        $rendered = $json->renderItem( $struct );

        $this->response->json( [ 'issue' => $rendered ] );
    }

    /**
     * @throws Exception
     * @throws \TypeError
     */
    public function delete(): void {
        // Deleting an issue gives its penalty points back to a review row, so it too requires a
        // review credential: a translate password identifies no phase to give them back to.
        if ( $this->credentialSourcePage() === SourcePages::SOURCE_PAGE_TRANSLATE ) {
            throw new AuthorizationError( 'A revision password is required to delete an issue', 401 );
        }

        $issue = $this->validator->issue ?? throw new RuntimeException('Missing issue');

        $this->getDatabase()->begin();
        // Job, phase and review credential all come from the password this request authenticated
        // with, as resolved by ChunkPasswordValidator.
        $model = $this->_getSegmentTranslationIssueModel(
            $this->chunkReview->id_job ?? throw new RuntimeException( 'Missing job id' ),
            $this->chunkReview->review_password ?? throw new RuntimeException( 'Missing review password' ),
            $issue
        );

        $this->checkLoggedUserPermissions($issue, $this->chunk, $this->user);

        $model->delete();
        $this->getDatabase()->commit();

        $this->response->code( 200 );
    }

      /**
       * @throws RuntimeException
       */
      public function getComments(): void {
          $dao = new EntryCommentDao($this->getDatabase());

          $comments = $dao->findByIssueId(
              $this->validator->issue->id ?? throw new RuntimeException('Missing issue id')
          );

         $json = new TranslationIssueComment();
         $rendered = $json->render( $comments );
         $this->response->json( [ 'comments' => $rendered ] );
     }

     /**
      * @throws AuthorizationError
      * @throws NotFoundException
      * @throws RuntimeException
      * @throws TypeError
      */
      public function createComment(): void {
         $data = [
             'comment' => $this->request->param( 'message' ),
             'id_qa_entry' => (int)($this->validator->issue->id ?? throw new RuntimeException('Missing issue id')),
             'source_page' => $this->credentialSourcePage(),
             'uid' => (int)($this->user->uid ?? throw new RuntimeException('Missing user uid'))
         ];

         $dao = new EntryCommentDao($this->getDatabase());
         $entry = (new EntryDao($this->getDatabase()))->findById( $this->validator->issue->id ?? throw new RuntimeException('Missing issue id') );

        if ( empty( $entry ) ) {
            throw new NotFoundException( "Issue not found", 404 );
        }

        $dao->createComment( $data );

        $json = new TranslationIssueFormatter(new EntryCommentDao($this->getDatabase()));
        $rendered = $json->renderItem( $entry );

        $response = [ 'comment' => $rendered ];

        $this->response->json( $response );
    }

    /**
     * @param int $id_job
     * @param string $password
     * @param EntryStruct $issue
     *
     * @return TranslationIssueModel
     * @throws Exception
     * @throws \TypeError
     */
    protected function _getSegmentTranslationIssueModel( int $id_job, string $password, EntryStruct $issue ): TranslationIssueModel {
        return new TranslationIssueModel(
            $id_job,
            $password,
            $issue,
            new ChunkReviewDao($this->getDatabase()),
            new EntryDao($this->getDatabase()),
            new TranslationVersionDao($this->getDatabase()),
            new ProjectDao($this->getDatabase()),
            $this->user
        );
    }

    protected function registerValidators(): void {
        $this->appendValidator( new LoginValidator( $this ) );
        $jobValidator = new ChunkPasswordValidator( $this );
        $jobValidator->onSuccess( function () use ( $jobValidator ) {
            //enable dynamic loading (Factory) by callback hook on revision features
            $chunkReview = $jobValidator->getChunkReview();
            if ( $chunkReview === null ) {
                throw new NotFoundException( 'Chunk review not found' );
            }

            // The revision phase this request may act in comes from the password that matched,
            // which ChunkPasswordValidator has already stamped onto the chunk. Keep both the chunk
            // and its review row so no endpoint has to re-resolve them from request parameters.
            $this->chunk       = $jobValidator->getChunk();
            $this->chunkReview = $chunkReview;

            $this->validator = ( new SegmentTranslationIssueValidator( $this ) )->setChunkReview( $chunkReview );
            $this->validator->validate();
        } );
        $this->appendValidator( $jobValidator );
    }

    /**
     * The revision phase this request is allowed to act in.
     *
     * It is resolved from the password the caller presented — ChunkPasswordValidator stamps it onto
     * the chunk from the review row that matched — and never from the request body: a
     * client-declared revision_number proves nothing about which phase the caller may write to
     * (GHSA-7q94-2fmr-3p42). The parameter is no longer read anywhere: a client that still sends
     * it is ignored.
     *
     * @return int
     */
    private function credentialSourcePage(): int {
        return $this->chunk->getSourcePage() ?: SourcePages::SOURCE_PAGE_TRANSLATE;
    }

    private function getVersionNumber(): int {
        if ( null !== $this->request->param( 'version_number' ) ) {
            return (int)$this->request->param( 'version_number' );
        }

        return (int)$this->validator->translation->version_number;
    }

    /**
     * @throws AuthorizationError
     * @throws Exception
     */
    private function checkLoggedUserPermissions(EntryStruct $entry, JobStruct $job, UserStruct $loggerUser): void
    {
        if($entry->uid === $loggerUser->uid){
            return;
        }

        $owner = (new UserDao($this->getDatabase()))->getByEmail($job->owner);

        if($owner === null){
            throw new AuthorizationError( "Job owner not found. Not authorized", 401 );
        }

        if($owner->uid === $loggerUser->uid){
            return;
        }

        // Anyone else must belong to the team that owns the project.
        $project = $job->getProject(new ProjectDao($this->getDatabase()));
        (new ProjectAccessValidator($this, $project, $loggerUser))->validate();
    }
}