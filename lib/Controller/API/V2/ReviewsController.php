<?php

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\ValidationError;
use Controller\API\Commons\Validators\ProjectAccessValidator;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Controller\API\Commons\Validators\TeamProjectValidator;
use Exception;
use Model\Jobs\ChunkDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use RevisionFactory;

class ReviewsController extends KleinController {
    /**
     * @var ProjectStruct $project
     */
    protected ProjectStruct $project;

    /**
     * @var JobStruct
     */
    protected JobStruct $chunk;

    protected int $nextSourcePage;

    /**
     * @var ChunkReviewStruct
     */
    protected ChunkReviewStruct $latestChunkReview;

    /**
     * @throws Exception
     */
    public function createReview() {

        // create a new chunk revision password
        $records = RevisionFactory::initFromProject( $this->project )->getRevisionFeature()->createQaChunkReviewRecords(
                [ $this->chunk ],
                $this->project,
                [
                        'source_page' => $this->nextSourcePage
                ]
        );

        // destroy project data cache
        ( new ProjectDao() )->destroyCacheForProjectData( $this->project->id, $this->project->password );

        // destroy the 5 minutes chunk review cache
        $chunk = ( new ChunkDao() )->getByIdAndPassword( $records[ 0 ]->id_job, $records[ 0 ]->password );
        ( new ChunkReviewDao() )->destroyCacheForFindChunkReviews( $chunk );

        $this->response->json( [
                        'chunk_review' => [
                                'id'              => $records[ 0 ]->id,
                                'id_job'          => $records[ 0 ]->id_job,
                                'review_password' => $records[ 0 ]->review_password
                        ]
                ]
        );
    }

    protected function afterConstruct() {

        $Validator = new ProjectPasswordValidator( $this );

        $Validator->onSuccess( function () use ( $Validator ) {
            $this->project = $Validator->getProject();
        } )->onSuccess( function () {
            //add more specific validations, it's needed to append after the first validation run because we need the project struct
            ( new TeamProjectValidator( $this ) )->setProject( $this->project )->validate();
        } )->onSuccess( function () {
            ( new ProjectAccessValidator( $this, $this->project ) )->validate();
        } );

        $this->appendValidator( $Validator );

    }

    /**
     * add more specific validations
     *
     * @throws ValidationError
     */
    protected function afterValidate() {

        $post = $this->request->paramsPost();

        $requiredParams = [
                'id_job',
                'password',
        ];

        foreach ( $requiredParams as $requiredParam ) {
            if ( !isset( $post[ $requiredParam ] ) ) {
                throw new ValidationError( $requiredParam . ' param is not provided' );
            }
        }

        $id_job          = $post[ 'id_job' ];
        $password        = $post[ 'password' ];
        $revision_number = 2;

        $chunkReviewDao = new ChunkReviewDao();

        // check if the $revision_number exists
        if ( false === $chunkReviewDao->exists( $id_job, $password, $revision_number ) ) {
            throw new ValidationError( "Revision " . ( $revision_number - 1 ) . " link does not exists." );
        }

        // check if the $revision_number + 1 exists
        if ( true === $chunkReviewDao->exists( $id_job, $password, ( $revision_number + 1 ) ) ) {
            throw new ValidationError( "Revision " . $revision_number . " link already exists." );
        }

        $this->nextSourcePage    = $revision_number + 1;
        $this->latestChunkReview = $chunkReviewDao->findLastReviewByJobIdPasswordAndSourcePage( $id_job, $password, $revision_number );

        if ( $this->latestChunkReview->id_project != $this->project->id ) {
            throw new ValidationError( "Job id / password combination is not in projects list" );
        }

        $this->chunk = $this->latestChunkReview->getChunk();
    }
}