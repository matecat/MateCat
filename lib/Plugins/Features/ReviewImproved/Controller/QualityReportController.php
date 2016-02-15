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

class QualityReportController {

    /**
     * @var \PHPTAL
     */
    private $view;
    private $request;
    private $response;
    private $service;


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

    public function __construct( \Klein\Request $request, \Klein\Response $response, $service) {
        $this->request = $request;
        $this->response = $response;
        $this->service = $service;
    }

    public function setView( $template_name ) {
        $this->view = new \PHPTAL( $template_name );
    }

    public function respond() {
        $model = $this->getModel() ;

        $decorator = new QualityReportDecorator( $model );

        $decorator->decorate( $this->view );

        $this->response->body( $this->view->execute() );
        $this->response->send();
    }

    /**
     * @throws \Exceptions_RecordNotFound
     */
    private function getModel() {
        $this->model = new QualityReportModel( $this->findChunk() );
        return $this->model ;
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