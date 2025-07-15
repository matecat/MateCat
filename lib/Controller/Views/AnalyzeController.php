<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 13/05/25
 * Time: 18:49
 *
 */

namespace Controller\Views;

use Controller\Abstracts\BaseKleinViewController;
use Controller\Abstracts\IController;
use Controller\API\Commons\ViewValidators\ViewLoginRedirectValidator;
use Exception;
use Model\ActivityLog\Activity;
use Model\ActivityLog\ActivityLogStruct;
use Model\Analysis\Status;
use Model\Jobs\ChunkDao;
use Model\Jobs\JobDao;
use Model\Projects\ProjectDao;
use Utils\Analysis\Health;
use Utils\Registry\AppConfig;
use Utils\Templating\PHPTalBoolean;
use Utils\Templating\PHPTalMap;
use Utils\Tools\Utils;

class AnalyzeController extends BaseKleinViewController implements IController {

    protected function afterConstruct() {
        $this->appendValidator( new ViewLoginRedirectValidator( $this ) );
    }

    /**
     * External EndPoint for outsourcing Login Service or for all in one-login and Confirm Order
     *
     * If a login service exists, it can return a token authentication on the Success page.
     *
     * That token will be sent back to the review/confirm page on the provider website to grant it logged
     *
     * The success Page must be set in the concrete subclass of "AbstractProvider"
     *  Ex: "OutsourceTo\Translated"
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

        $projectStruct = ProjectDao::findById( $pid, 60 * 60 );

        if ( empty( $projectStruct ) ) {
            $this->setView( "project_not_found.html", [], 404 );
            $this->render();
        }

        if ( !empty( $jid ) ) {

            // we are looking for a chunk
            $chunkStruct = JobDao::getByIdAndPassword( $jid, $pass );
            if ( empty( $chunkStruct ) || $chunkStruct->isDeleted() ) {
                $this->setView( "job_not_found.html", [], 404 );
                $this->render();
            }

            $this->setView( "jobAnalysis.html", [
                    'jid'                  => $jid,
                    'job_password'         => $chunkStruct->password,
                    'project_access_token' => sha1( $projectStruct->id . $projectStruct->password ),
            ] );

        } else {

            $chunks = ( new ChunkDao )->getByProjectID( $projectStruct->id );

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

        $projectData    = ProjectDao::getProjectAndJobData( $pid );
        $analysisStatus = new Status( $projectData, $this->featureSet, $this->user );

        $model = $analysisStatus->fetchData()->getResult();

        $this->addParamsToView( [
                'pid'                     => $projectStruct->id,
                'project_status'          => $projectStruct->status_analysis,
                'outsource_service_login' => $this->_outsource_login_API,
                'showModalBoxLogin'       => new PHPTalBoolean( !$this->isLoggedIn() ),
                'project_plugins'         => new PHPTalMap( $this->featureSet->filter( 'appendInitialTemplateVars', $this->featureSet->getCodes() ) ?? [] ),
                'num_segments'            => $model->getSummary()->getTotalSegments(),
                'num_segments_analyzed'   => $model->getSummary()->getSegmentsAnalyzed(),
                'daemon_misconfiguration' => new PHPTalBoolean( Health::thereIsAMisconfiguration() ),
                'json_jobs'               => json_encode( $model ),
                'split_enabled'           => new PHPTalBoolean( true ),
                'enable_outsource'        => new PHPTalBoolean( AppConfig::$ENABLE_OUTSOURCE ),
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