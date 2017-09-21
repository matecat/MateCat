<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/09/2017
 * Time: 15:57
 */

namespace Features\Dqf\Controller\API;

use API\App\AbstractStatefulKleinController;
use Chunks_ChunkDao;
use Features\Dqf\Model\CatAuthorizationModel;
use LQA\ChunkReviewDao;

class GenericController extends AbstractStatefulKleinController {

    // Lock the job to the DQF user
    public function assignProject() {
        $reviewItem = ChunkReviewDao::findByReviewPasswordAndJobId($this->request->id_job, $this->request->password ) ;
        if ( !$reviewItem ) {
            $chunk = Chunks_ChunkDao::getByIdAndPassword($this->request->id_job, $this->request->password);
        }
        else {
            $chunk = $reviewItem->getChunk() ;
        }

        $aut = new CatAuthorizationModel( $chunk->getJob(), !!$reviewItem ) ;
        $aut->assignJobToUser( $this->getUser() );
        $this->response->code(200) ;
    }

}