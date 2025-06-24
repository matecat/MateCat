<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 08/11/2017
 * Time: 15:24
 */

namespace Controller\API\V2;

use API\V2\Json\SegmentVersion;
use Chunks_ChunkDao;
use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\JobPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\SegmentTranslation;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Exception;
use Exceptions\NotFoundException;
use Features\TranslationVersions\Model\TranslationVersionDao;
use Jobs_JobStruct;
use ReflectionException;

class ReviseTranslationIssuesController extends KleinController {
    use ChunkNotFoundHandlerTrait;

    /**
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function afterConstruct() {
        $validator = new JobPasswordValidator( $this );
        $validator->onSuccess( function () use ( $validator ) {
            $this->featureSet->loadForProject( $validator->getJob()->getProject() );
        } );
        $this->appendValidator( $validator );
        $this->appendValidator( new SegmentTranslation( $this ) );
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws Exception
     */
    public function index() {
        $records = ( new TranslationVersionDao() )->setCacheTTL( 0 )->getVersionsForRevision(
                $this->request->param( 'id_job' ),
                $this->request->param( 'id_segment' )
        );

        $chunk = Chunks_ChunkDao::getByIdAndPassword( $this->params[ 'id_job' ], $this->params[ 'password' ] );

        $this->chunk = $chunk;
        $this->return404IfTheJobWasDeleted();

        $version_formatter = new SegmentVersion( $chunk, $records, true, $this->featureSet );
        $this->response->json( [ 'versions' => $version_formatter->render() ] );
    }

    /**
     * @param Jobs_JobStruct|null $chunk
     */
    public function setChunk( Jobs_JobStruct $chunk = null ) {
        $this->chunk = $chunk;
    }
}