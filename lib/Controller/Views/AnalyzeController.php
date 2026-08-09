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
use Controller\API\Commons\Validators\ProjectAccessValidator;
use Controller\API\Commons\ViewValidators\ViewLoginRedirectValidator;
use Exception;
use Model\ActivityLog\Activity;
use Model\ActivityLog\ActivityLogStruct;
use Model\Analysis\Status;
use Model\FeaturesBase\Hook\Event\Filter\AppendInitialTemplateVarsEvent;
use Model\FeaturesBase\Hook\Event\Filter\IsAnInternalUserEvent;
use Model\Jobs\JobDao;
use Model\Projects\ProjectDao;
use Throwable;
use Utils\AsyncTasks\Workers\Analysis\Health;
use Utils\Registry\AppConfig;
use Utils\Templating\PHPTalBoolean;
use Utils\Templating\PHPTalMap;
use Utils\Tools\Utils;

class AnalyzeController extends BaseKleinViewController implements IController
{

    private ?ProjectDao $projectDao = null;

    private function getProjectDao(): ProjectDao
    {
        return $this->projectDao ??= new ProjectDao($this->getDatabase());
    }

    protected function registerValidators(): void
    {
        $this->appendValidator(new ViewLoginRedirectValidator($this));
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

    /**
     * @return array<string, mixed>
     */
    private function validateTheRequest(): array
    {
        $filterArgs = [
            'pid' => ['filter' => FILTER_SANITIZE_NUMBER_INT],
            'jid' => ['filter' => FILTER_SANITIZE_NUMBER_INT],
            'password' => [
                'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            ]
        ];

        return filter_var_array($this->request->paramsNamed()->all(), $filterArgs);
    }

    /**
     * @throws Exception
     * @throws \TypeError
     */
    public function renderView(): void
    {
        $postInput = $this->validateTheRequest();

        $pid = $postInput['pid'];
        $jid = $postInput['jid'];
        $pass = $postInput['password'];

        $projectStruct = $this->getProjectDao()->findById($pid, 60 * 60);

        if (empty($projectStruct)) {
            $this->setView("project_not_found.html", [], 404);
            $this->render();
        }

        if (!empty($jid)) {
            // we are looking for a chunk
            $chunkStruct = (new JobDao($this->getDatabase()))->getByIdAndPassword($jid, $pass);
            if (empty($chunkStruct) || $chunkStruct->isDeleted()) {
                $this->setView("job_not_found.html", [], 404);
                $this->render();
            }

            $this->setView("jobAnalysis.html", [
                'id_job' => $jid,
                // The page distinguishes itself from the project-wide analyze view by this flag alone.
                'jobAnalysis' => new PHPTalBoolean(true),
                'job_password' => $chunkStruct->password,
                'project_access_token' => sha1($projectStruct->id . $projectStruct->password),
            ]);
        } else {
            $chunks = (new JobDao($this->getDatabase()))->getNotDeletedByProjectId((int)$projectStruct->id);

            $notDeleted = array_filter($chunks, function ($element) {
                return !$element->isDeleted(); //retain only jobs which are not deleted
            });

            if ($projectStruct->password != $pass || empty($notDeleted)) {
                $this->setView("project_not_found.html", [], 404);
                $this->render();
            }

            $this->setView("analyze.html", [
                'password' => $projectStruct->password,
            ]);
        }

        $this->featureSet->loadForProject($projectStruct);

        $projectData = $this->getProjectDao()->getProjectAndJobData($pid);
        $analysisStatus = new Status($projectData, $this->featureSet, $this->user);

        $model = $analysisStatus->fetchData()->getResult();

        $appendInitialTemplateVarsEvent = new AppendInitialTemplateVarsEvent($this->featureSet->getCodes());
        $this->featureSet->dispatch($appendInitialTemplateVarsEvent);

        // Two separate questions reach the page, and conflating them is what the naming avoids:
        // split_feature_available says the split affordance exists in this UI at all, while split_enabled
        // is the button state — whether this caller may click it.
        //
        // Grey out the split button for a caller the split endpoints would refuse. This mirrors
        // SplitJobController's authorization exactly — same internal-user exemption, same validator — and
        // that is the point: a button offering an action the API answers 403 to is its own bug report.
        // The mirror is not the enforcement, only its reflection; the endpoints check independently, so
        // getting this wrong shows the wrong button rather than granting anything.
        //
        // Failure is swallowed on purpose. The question asked is "would the API allow this?", and every
        // way of answering no — not the owner, not in the team, or the lookup itself failing — resolves
        // to hiding the button. A page that renders without the button beats a page that does not render.
        $split_enabled = true;
        $event = $this->getFeatureSet()->dispatch(new IsAnInternalUserEvent($this->getUser()->email ?? ''));
        if (!$event->isInternal()) {
            try {
                (new ProjectAccessValidator($this, $projectStruct, $this->getUser()))->validate();
            } catch (Throwable) {
                $split_enabled = false;
            }
        }

        $this->addParamsToView([
            'id_project' => $projectStruct->id,
            'status' => $projectStruct->status_analysis,
            'outsource_service_login' => $this->_outsource_login_API,
            'showModalBoxLogin' => new PHPTalBoolean(!$this->isLoggedIn()),
            'project_plugins' => new PHPTalMap($appendInitialTemplateVarsEvent->getCodes()),
            'totalSegments' => $model->getSummary()->getTotalSegments(),
            'totalAnalyzed' => $model->getSummary()->getSegmentsAnalyzed(),
            'daemon_warning' => new PHPTalBoolean(Health::thereIsAMisconfiguration()),
            // Handed over unencoded: the page configuration is serialised once, as a whole, so a
            // pre-encoded string here would reach the page as a quoted string instead of an object.
            'jobs' => $model,
            'splitEnabled' => new PHPTalBoolean($split_enabled),
            'splitFeatureAvailable' => new PHPTalBoolean(true),
            'enable_outsource' => new PHPTalBoolean(AppConfig::$ENABLE_OUTSOURCE),
            // Never assigned before, so the template's `| string:false` default is what the page has
            // always received. Stated outright rather than left to a fallback; whether it should mirror
            // CattoolController's !empty(DEFAULT_TM_KEY) is a separate question, recorded in the todo doc.
            'not_empty_default_tm_key' => new PHPTalBoolean(false),
        ]);

        $activity = new ActivityLogStruct();
        $activity->id_job = $chunkStruct->id ?? null;
        $activity->id_project = $projectStruct->id;
        $activity->action = ActivityLogStruct::ACCESS_ANALYZE_PAGE;
        $activity->ip = Utils::getRealIpAddr();
        $activity->uid = $this->user->uid;
        $activity->event_date = date('Y-m-d H:i:s');
        Activity::save($activity);

        $this->render();
    }

}
