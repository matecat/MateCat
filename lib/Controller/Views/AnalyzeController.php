<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 13/05/25
 * Time: 18:49
 *
 */

namespace Views;

use AbstractControllers\BaseKleinViewController;
use AbstractControllers\IController;
use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;
use Analysis\Health;
use API\App\Json\Analysis\AnalysisProject;
use API\Commons\ViewValidators\LoginRedirectValidator;
use Chunks_ChunkDao;
use Exception;
use INIT;
use Jobs_JobDao;
use Model\Analysis\Status;
use Projects_MetadataDao;
use Projects_ProjectDao;
use Utils;

class AnalyzeController extends BaseKleinViewController implements IController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginRedirectValidator( $this ) );
    }

    /**
     * External EndPoint for outsourcing Login Service or for all in one-login and Confirm Order
     *
     * If a login service exists, it can return a token authentication on the Success page.
     *
     * That token will be sent back to the review/confirm page on the provider website to grant it logged
     *
     * The success Page must be set in the concrete subclass of "OutsourceTo_AbstractProvider"
     *  Ex: "OutsourceTo_Translated"
     *
     *
     * Values from the quote result will be posted there anyway.
     *
     * @var string
     */
    protected string $_outsource_login_API = '//signin.translated.net/';

    private function validateTheRequest(): array {
        $filterArgs = [
                'pid'      => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'jid'      => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password' => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ]
        ];

        return filter_var_array( $this->request->paramsNamed()->all(), $filterArgs );
    }

    /**
     * @throws Exception
     */
    public function renderView() {

        $postInput = $this->validateTheRequest();

        $pid  = $postInput[ 'pid' ];
        $jid  = $postInput[ 'jid' ];
        $pass = $postInput[ 'password' ];

        $projectStruct = Projects_ProjectDao::findById( $pid, 60 * 60 );

        if ( empty( $projectStruct ) ) {
            $this->setView( "project_not_found.html", [], 404 );
            $this->render();
        }

        if ( !empty( $jid ) ) {

            // we are looking for a chunk
            $chunkStruct = Jobs_JobDao::getByIdAndPassword( $jid, $pass );
            if ( empty( $chunkStruct ) || $chunkStruct->isDeleted() ) {
                $this->setView( "project_not_found.html", [], 404 );
                $this->render();
            }

            $this->setView( "jobAnalysis.html", [
                    'jid'              => $jid,
                    'job_password'     => $pass,
                    'project_password' => $projectStruct->password,
            ] );

        } else {

            $chunks = ( new Chunks_ChunkDao )->getByProjectID( $projectStruct->id );

            $notDeleted = array_filter( $chunks, function ( $element ) {
                return !$element->isDeleted(); //retain only jobs which are not deleted
            } );

            if ( $projectStruct->password != $pass || empty( $notDeleted ) ) {
                $this->setView( "project_not_found.html", [], 404 );
                $this->render();
            }

            $this->setView( "analyze.html", [
                    'project_password' => $projectStruct->password,
            ] );

        }

        if ( $projectStruct ) {
            $this->featureSet->loadForProject( $projectStruct );
        }

        $projectMetaDataDao = new Projects_MetadataDao();
        $projectMetaData    = $projectMetaDataDao->get( $projectStruct->id, Projects_MetadataDao::FEATURES_KEY );

        $projectData    = Projects_ProjectDao::getProjectAndJobData( $pid );
        $analysisStatus = new Status( $projectData, $this->featureSet, $this->user );

        /**
         * @var AnalysisProject $model
         */
        $model = $analysisStatus->fetchData()->getResult();

        $this->addParamsToView( [
                'pid'                     => $projectStruct->id,
                'project_status'          => $projectStruct->status_analysis,
                'outsource_service_login' => $this->_outsource_login_API,
                'showModalBoxLogin'       => !$this->isLoggedIn(),
                'project_plugins'         => $this->featureSet->filter( 'appendInitialTemplateVars', explode( ",", $projectMetaData->value ) ) ?? [],
                'num_segments'            => $model->getSummary()->getTotalSegments(),
                'num_segments_analyzed'   => $model->getSummary()->getSegmentsAnalyzed(),
                'daemon_misconfiguration' => var_export( Health::thereIsAMisconfiguration(), true ),
                'json_jobs'               => json_encode( $model ),
                'split_enabled'           => true,
                'enable_outsource'        => INIT::$ENABLE_OUTSOURCE,
        ] );

        $activity             = new ActivityLogStruct();
        $activity->id_job     = $chunkStruct->id ?? null;
        $activity->id_project = $projectStruct->id;
        $activity->action     = ActivityLogStruct::ACCESS_ANALYZE_PAGE;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->user->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );

        $this->render();
    }

}