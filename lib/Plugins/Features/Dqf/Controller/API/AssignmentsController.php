<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 10/10/2017
 * Time: 11:26
 */

namespace Features\Dqf\Controller\API;


use API\App\AbstractStatefulKleinController;
use API\V2\Json\User;
use Features\Dqf\Model\CatAuthorizationModel;
use Projects_ProjectDao;

class AssignmentsController extends AbstractStatefulKleinController {

    public function listAssignments() {
        $project = Projects_ProjectDao::findByIdAndPassword( $this->request->id_project, $this->request->password  ) ;

        $results = array_map( function( \Chunks_ChunkStruct $chunk ) {
            $data =  [
                    'id' => $chunk->id,
                    'password' => $chunk->password,
            ];

            $review = new CatAuthorizationModel($chunk->getJob(), true );
            $translate = new CatAuthorizationModel($chunk->getJob(), false );

            $review_user = $review->getAuthorizedUser();
            if ( $review_user ) {
                $data['review_user'] = User::renderItem( $review_user );
            }

            $translate_user = $translate->getAuthorizedUser();
            if ( $translate_user ) {
                $data['translate_user'] = User::renderItem( $translate_user );
            }

            return $data ;
        }, $project->getChunks() ) ;

        $this->response->json( $results );

    }

}