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

        $decorator = new QualityReportDecorator( $this->getModel() );
        $decorator->decorate();

        $this->response->body( $this->view->execute() );
        $this->response->send();
    }


    /**
     * @throws \Exceptions_RecordNotFound
     */
    private function getModel() {
        $this->model = new QualityReportModel( $this->findJob() );
        return $this->model ;
    }

    /**
     * @return \Jobs_JobStruct
     * @throws \Exceptions_RecordNotFound
     */
    private function findJob() {
        $this->job = \Jobs_JobDao::getByIdAndPassword(
                $this->request->param('id_job'),
                $this->request->param('password')
        );

        if (! $this->job ) {
            throw new \Exceptions_RecordNotFound();
        }

        if (! $this->job->getProject()->isFeatureEnabled('review_improved')) {
            throw new \Exceptions_RecordNotFound();
        }

        return $this->job ;
    }
}