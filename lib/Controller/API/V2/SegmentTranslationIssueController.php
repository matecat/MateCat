<?php

namespace Controller\API\V2;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\SegmentTranslationIssueValidator;
use Exception;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\EntryCommentDao;
use Model\Projects\ProjectDao;
use Model\LQA\EntryDao as EntryDao;
use Model\LQA\EntryStruct;
use Model\Teams\MembershipDao;
use Model\Teams\TeamDao;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use Plugins\Features\ReviewExtended\ReviewUtils;
use Plugins\Features\ReviewExtended\TranslationIssueModel;
use Plugins\Features\TranslationVersions\Model\TranslationVersionDao;
use RuntimeException;
use TypeError;
use View\API\V2\Json\SegmentTranslationIssue as TranslationIssueFormatter;
use View\API\V2\Json\TranslationIssueComment;

class SegmentTranslationIssueController extends AbstractStatefulKleinController {

    /**
     * @var SegmentTranslationIssueValidator
     */
    private SegmentTranslationIssueValidator $validator;

    /**
     * @throws RuntimeException
     */
    public function index(): void {
        $result = (new EntryDao($this->getDatabase()))->findAllByTranslationVersion(
            $this->validator->translation->id_segment,
            $this->validator->translation->id_job,
            $this->getVersionNumber()
        );

        $json = new TranslationIssueFormatter();
        $rendered = $json->render( $result );

        $this->response->json( [ 'issues' => $rendered ] );
    }

    /**
     * @throws ValidationError
     * @throws RuntimeException
     * @throws Exception
     * @throws \TypeError
     */
    public function create(): void {
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
            'source_page' => ReviewUtils::revisionNumberToSourcePage( $this->request->param( 'revision_number' ) ),
        ];

        $this->getDatabase()->begin();

        $struct = new EntryStruct( $data );

        $model = $this->_getSegmentTranslationIssueModel(
            $this->request->param( 'id_job' ),
            $this->request->param( 'password' ),
            $struct
        );

        $struct = $model->save();

        $this->getDatabase()->commit();

        $json     = new TranslationIssueFormatter();
        $rendered = $json->renderItem( $struct );

        $this->response->json( [ 'issue' => $rendered ] );
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function update(): void {
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
        $chunkReviewStruct = $chunkReviewDao->findByReviewPasswordAndJobId($this->request->param( 'password' ), $this->request->param( 'id_job' ));

        if ( $chunkReviewStruct === null ) {
            throw new NotFoundException( "Job not found", 404 );
        }

        $jobStruct = $chunkReviewStruct->getChunk(new JobDao($this->getDatabase()));

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

        $oldStruct->setDefaults();

        $newStruct     = new EntryStruct( $data );
        $newStruct->id = $data[ 'id_issue' ];
        $newStruct->setDefaults();

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

        $json = new TranslationIssueFormatter();
        $rendered = $json->renderItem( $struct );

        $this->response->json( [ 'issue' => $rendered ] );
    }

    /**
     * @throws Exception
     * @throws \TypeError
     */
    public function delete(): void {
        $issue = $this->validator->issue ?? throw new RuntimeException('Missing issue');

        $this->getDatabase()->begin();
        $model = $this->_getSegmentTranslationIssueModel(
            $this->request->param( 'id_job' ),
            $this->request->param( 'password' ),
            $issue
        );

        $chunkReviewStruct = (new ChunkReviewDao($this->getDatabase()))->findByReviewPasswordAndJobId($this->request->param( 'password' ), $this->request->param( 'id_job' ));

        if ( $chunkReviewStruct === null ) {
            throw new NotFoundException( "Job not found", 404 );
        }

        $jobStruct = $chunkReviewStruct->getChunk(new JobDao($this->getDatabase()));

        $this->checkLoggedUserPermissions($issue, $jobStruct, $this->user);

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
             'source_page' => (int)($this->request->param( 'source_page' ) ?? throw new RuntimeException('Missing source_page')),
             'uid' => (int)($this->user->uid ?? throw new RuntimeException('Missing user uid'))
         ];

         $dao = new EntryCommentDao($this->getDatabase());
         $entry = (new EntryDao($this->getDatabase()))->findById( $this->validator->issue->id ?? throw new RuntimeException('Missing issue id') );

        if ( empty( $entry ) ) {
            throw new NotFoundException( "Issue not found", 404 );
        }

        $dao->createComment( $data );

        $json = new TranslationIssueFormatter();
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
            new ProjectDao($this->getDatabase())
        );
    }

    protected function registerValidators(): void {
        $this->appendValidator( new LoginValidator( $this ) );
        $jobValidator = new ChunkPasswordValidator( $this );
        $jobValidator->onSuccess( function () use ( $jobValidator ) {
            //enable dynamic loading (Factory) by callback hook on revision features
            $this->validator = ( new SegmentTranslationIssueValidator( $this ) )->setChunkReview( $jobValidator->getChunkReview() );
            $this->validator->validate();
        } );
        $this->appendValidator( $jobValidator );
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
            throw new AuthorizationError( "Job owner not found. Not Authorized", 401 );
        }

        if($owner->uid === $loggerUser->uid){
            return;
        }

        $project = $job->getProject(new ProjectDao($this->getDatabase()));
        $team = $project->id_team !== null ? (new TeamDao($this->getDatabase()))->findById($project->id_team) : null;

        if ($team === null || $team->id === null) {
            throw new AuthorizationError( "Team not found. Not Authorized", 401 );
        }

        $mDao = new MembershipDao($this->getDatabase());

        foreach ($mDao->getMemberListByTeamId($team->id) as $member){
            if($member->uid === $loggerUser->uid){
                return;
            }
        }

        throw new AuthorizationError( "Not Authorized", 401 );
    }
}