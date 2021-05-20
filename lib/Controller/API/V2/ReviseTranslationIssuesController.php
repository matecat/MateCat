<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 08/11/2017
 * Time: 15:24
 */

namespace API\V2;


use API\V2\Json\SegmentVersion;
use API\V2\Validators\JobPasswordValidator;
use API\V2\Validators\SegmentTranslation;
use Features\TranslationVersions\Model\TranslationVersionDao;

class ReviseTranslationIssuesController extends KleinController {

    public function afterConstruct() {
        $validator = new JobPasswordValidator( $this );
        $validator->onSuccess( function () use ( $validator ) {
            $this->featureSet->loadForProject( $validator->getJob()->getProject() );
        } );
        $this->appendValidator( $validator );
        $this->appendValidator( new SegmentTranslation( $this->request ) );
    }

    public function index() {
        $records = ( new TranslationVersionDao() )->setCacheTTL( 0 )->getVersionsForRevision(
                $this->request->id_job,
                $this->request->id_segment
        );

        $chunk = \Chunks_ChunkDao::getByIdAndPassword($this->params[ 'id_job' ], $this->params[ 'password' ]);

        $version_formatter = new SegmentVersion( $chunk, $records, true, $this->featureSet );
        $this->response->json( [ 'versions' => $version_formatter->render() ] );
    }

}