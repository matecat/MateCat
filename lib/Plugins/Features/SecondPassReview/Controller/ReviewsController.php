<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 01/04/2019
 * Time: 17:18
 */

namespace Features\SecondPassReview\Controller;

use API\V2\Exceptions\ValidationError;
use API\V2\KleinController;
use API\V2\Validators\ProjectAccessValidator;
use API\V2\Validators\ProjectPasswordValidator;
use API\V2\Validators\TeamProjectValidator;
use Exception;
use LQA\ChunkReviewDao;
use LQA\ChunkReviewStruct;
use Projects_ProjectStruct;
use RevisionFactory;

class ReviewsController extends KleinController {

    /**
     * @var \Projects_ProjectStruct $project
     */
    protected $project;

    /**
     * @var ChunkReviewStruct
     */
    protected $chunk;

    protected $nextSourcePage;

    /**
     * @var ChunkReviewStruct
     */
    protected $latestChunkReview;

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

        $Validator  = new ProjectPasswordValidator( $this );
        $Controller = $this;
        $Validator->onSuccess( function () use ( $Validator, $Controller ) {
            $Controller->setProject( $Validator->getProject() );
            //add more specific validations, it's needed to append after the first validation run because we need the project struct
            ( new TeamProjectValidator( $this ) )->setProject( $this->project )->validate();
            ( new ProjectAccessValidator( $this ) )->setProject( $this->project )->validate();
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
                'revision_number',
        ];

        foreach ($requiredParams as $requiredParam){
            if ( !isset( $post[ $requiredParam ] ) ) {
                throw new ValidationError( $requiredParam . ' param is not provided' );
            }
        }

        $this->latestChunkReview = ( new ChunkReviewDao() )->findByJobIdPasswordAndSourcePage( $post[ 'id_job' ],$post[ 'password' ],$post[ 'revision_number' ] );
        if ( $this->latestChunkReview->id_project != $this->project->id and $this->latestChunkReview->password != $this->project->password ) {
            throw new ValidationError( "Job id / password combination is not in projects list" );
        }

        $this->nextSourcePage = $post[ 'revision_number' ] + 1;
        if ( $this->latestChunkReview->source_page + 1 != $this->nextSourcePage ) {
            throw new ValidationError( "This revision number is not allowed" );
        }

        $this->chunk = $this->latestChunkReview->getChunk();

    }

    public function setProject( $project ) {
        $this->project = $project;
    }

}