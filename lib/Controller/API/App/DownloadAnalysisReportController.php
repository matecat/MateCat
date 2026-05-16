<?php

namespace Controller\API\App;

use Controller\Abstracts\AbstractDownloadController;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Exception;
use InvalidArgumentException;
use Model\ActivityLog\Activity;
use Model\ActivityLog\ActivityLogStruct;
use Model\Analysis\XTRFStatus;
use Model\FeaturesBase\FeatureSet;
use Model\Projects\ProjectDao;
use ReflectionException;
use TypeError;
use Utils\Tools\Utils;
use View\API\Commons\ZipContentObject;

class DownloadAnalysisReportController extends AbstractDownloadController
{

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
        $this->appendValidator(new ProjectPasswordValidator($this));
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function download(): void
    {
        $this->featureSet = new FeatureSet();
        $request = $this->validateTheRequest();
        $projectId = (int)$request['id_project'];
        $_project_data = ProjectDao::getProjectAndJobData($projectId);
        $this->id_job = (int)$_project_data[0]['jid'];

        $this->featureSet->loadForProject(ProjectDao::staticFindById($projectId, 60 * 60 * 24) ?? throw new Exception("Project not found"));

        $analysisStatus = new XTRFStatus($_project_data, $this->featureSet);
        $outputContent = $analysisStatus->fetchData()->getResultArray();

        // cast $outputContent elements to ZipContentObject
        foreach ($outputContent as $key => $__output_content_elem) {
            $outputContent[$key] = new ZipContentObject([
                'output_filename' => $key,
                'document_content' => $__output_content_elem,
                'input_filename' => $key,
            ]);
        }

        $this->outputContent = $this->composeZip($outputContent);
        $this->_filename = $_project_data[0]['pname'] . ".zip";

        $activity = new ActivityLogStruct();
        $activity->id_job = (int)$_project_data[0]['jid'];
        $activity->id_project = $projectId; //assume that all rows have the same project id
        $activity->action = ActivityLogStruct::DOWNLOAD_ANALYSIS_REPORT;
        $activity->ip = Utils::getRealIpAddr();
        $activity->uid = $this->user->uid;
        $activity->event_date = date('Y-m-d H:i:s');
        Activity::save($activity);

        $this->finalize();
    }

    /**
     * @return array<string, mixed>
     * @throws ReflectionException
     * @throws Exception
     * @throws InvalidArgumentException
     */
    private function validateTheRequest(): array
    {
        $id_project = (string)filter_var($this->request->param('id_project'), FILTER_SANITIZE_NUMBER_INT);
        $password = filter_var($this->request->param('password'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $download_type = filter_var($this->request->param('download_type'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);

        if (empty($id_project)) {
            throw new InvalidArgumentException("Id project not provided");
        }

        $project = ProjectDao::staticFindById((int)$id_project);

        if (empty($project)) {
            throw new InvalidArgumentException("Wrong Id project provided", -10);
        }

        $this->project = $project;

        return [
            'project' => $project,
            'id_project' => $id_project,
            'password' => $password,
            'download_type' => $download_type,
        ];
    }
}
