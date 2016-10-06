<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 2/11/16
 * Time: 5:32 PM
 */

namespace Features\ReviewImproved\Controller;

use Features\ReviewImproved\Model\QualityReportModel ;
use Features\ReviewImproved\Decorator\QualityReportDecorator ;

class QualityReportController extends \BaseKleinViewController  {

    /**
     * @var \Jobs_JobStruct
     */
    private $job ;

    /**
     * @var \Chunks_ChunkStruct
     */
    private $chunk ;
    /**
     * @var QualityReportModel
     */
    private $model ;

    public function respond() {

        $this->setLoggedUser();

        $this->setDefaultTemplateData();

        $model = $this->getModel() ;
        $decorator = new QualityReportDecorator( $model );

        $decorator->setDownloadURI( $this->downloadURI() );
        $decorator->decorate( $this->view );

        $this->response->body( $this->view->execute() );

        if ( $this->isDownload()  ) {
            $this->response->header(
                    'Content-Disposition',
                    "attachment; filename={$decorator->getFilenameForDownload()}"
            );
        }
        $this->response->send();
    }

    private function downloadURI() {
        list($uri, $query) = explode('?', $this->request->uri());
        return $uri  . '?download=1';
    }

    /**
     * @throws \Exceptions_RecordNotFound
     */
    private function getModel() {
        $this->model = new QualityReportModel( $this->findChunk() );
        return $this->model ;
    }

    private function isDownload() {
        $param = $this->request->paramsGet('download');
        return isset( $param['download'] );
    }
    /**
     * @return \Chunks_ChunkDao
     * @throws \Exceptions_RecordNotFound
     */
    private function findChunk() {
        $this->chunk = \Chunks_ChunkDao::getByIdAndPassword(
                $this->request->param('id_job'),
                $this->request->param('password')
        );

        if (! $this->chunk ) {
            throw new \Exceptions_RecordNotFound();
        }

        if (! $this->chunk->getProject()->isFeatureEnabled('review_improved')) {
            throw new \Exceptions_RecordNotFound();
        }

        return $this->chunk ;
    }
}