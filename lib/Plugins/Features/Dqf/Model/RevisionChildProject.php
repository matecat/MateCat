<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 08/08/2017
 * Time: 15:41
 */

namespace Features\Dqf\Model;

use Chunks_ChunkStruct;
use Features\Dqf\Service\ChildProjectRevisionBatchService;
use Features\ReviewImproved\Model\QualityReportModel;
use Jobs\MetadataDao;
use LQA\ChunkReviewDao;
use Users_UserDao;

class RevisionChildProject {

    protected $chunk ;

    public function __construct( Chunks_ChunkStruct $chunk ) {
        $this->chunk = $chunk ;

        $uid = ( new MetadataDao() )->get( $chunk->id, $chunk->password, 'dqf_revise_user' )->value ;
        $this->dqfTranslateUser = new UserModel( ( new Users_UserDao() )->getByUid( $uid ) );

        $ownerUser = ( new Users_UserDao() )->getByEmail( $this->chunk->getProject()->id_customer );
        $this->ownerSession = ( new UserModel( $ownerUser ) )->getSession();
        $this->ownerSession->login();

        $this->userSession = $this->dqfTranslateUser->getSession();
        $this->userSession->login();

        $this->revisionBatchService = new ChildProjectRevisionBatchService($this->userSession) ;

        $this->dqfChildProjects = ( new DqfProjectMapDao() )->getByChunk( $this->chunk ) ;
    }

    public function submitRevisionData() {

        // 1. review_improved is already triggered here. Read revision data for the chunk.
        //
        $qualityModel = new QualityReportModel( $this->chunk );
        $structure = $qualityModel->getStructure();

        var_dump( $structure['chunk'] [ 'files' ][ 0 ][ 'segments'] );

    }
}