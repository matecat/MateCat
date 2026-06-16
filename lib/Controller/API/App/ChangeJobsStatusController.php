<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Model\Exceptions\NotFoundException;
use Model\Jobs\JobDao;
use Model\Projects\ProjectDao;
use Model\Translations\SegmentTranslationDao;
use ReflectionException;
use Utils\Constants\JobStatus;
use Utils\Tools\Utils;

class ChangeJobsStatusController extends KleinController
{

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws Exception
     */
    public function changeStatus(): void
    {
        $request = $this->validateTheRequest();

        if ($request['res_type'] == "prj") {
            try {
                $project = (new ProjectDao($this->db()))->findByIdAndPassword((int)$request['res_id'], (string)$request['password']);
            } catch (Exception) {
                $msg = "Error : wrong password provided for Change Project Status \n\n " . var_export($this->request->paramsPost()->all(), true) . "\n";
                $this->logger->debug($msg);
                Utils::sendErrMailReport($msg);
                throw new NotFoundException("Job not found");
            }

            $chunks = $project->getJobs();
            $projectId = $project->id ?? throw new NotFoundException("Project not found");

            (new JobDao($this->db()))->updateAllJobsStatusesByProjectId((int)$projectId, $request['new_status']);

            $segmentTranslationDao = new SegmentTranslationDao($this->db());

            foreach ($chunks as $chunk) {
                $lastSegmentsList = $segmentTranslationDao->getMaxSegmentIdsFromJob($chunk);
                $segmentTranslationDao->updateLastTranslationDateByIdList($lastSegmentsList, Utils::mysqlTimestamp(time()));
            }
        } else {
            try {
                $firstChunk = (new JobDao($this->db()))->getByIdAndPasswordOrFail((int)$request['res_id'], (string)$request['password']);
            } catch (Exception) {
                $msg = "Error : wrong password provided for Change Job Status \n\n " . var_export($this->request->paramsPost()->all(), true) . "\n";
                $this->logger->debug($msg);
                Utils::sendErrMailReport($msg);
                throw new NotFoundException("Job not found");
            }

            $segmentTranslationDao = new SegmentTranslationDao($this->db());
            (new JobDao($this->db()))->updateJobStatus($firstChunk, $request['new_status']);
            $lastSegmentsList = $segmentTranslationDao->getMaxSegmentIdsFromJob($firstChunk);
            $segmentTranslationDao->updateLastTranslationDateByIdList($lastSegmentsList, Utils::mysqlTimestamp(time()));
        }

        $this->response->json([
            'errors' => [],
            'code' => 1,
            'data' => 'OK',
            'status' => $request['new_status']
        ]);
    }

    /**
     * @return array{pn: string|false, res_type: string|false, res_id: int|false, password: string|false, new_status: string}
     * @throws Exception
     */
    private function validateTheRequest(): array
    {
        $pn = filter_var($this->request->param('pn'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);
        $id = filter_var($this->request->param('id'), FILTER_VALIDATE_INT);
        $res = filter_var($this->request->param('res'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW]);
        $password = filter_var($this->request->param('password'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW]);
        $new_status = filter_var($this->request->param('new_status'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW]);

        if ($new_status === false || !JobStatus::isAllowedStatus($new_status)) {
            throw new Exception("Invalid Status");
        }

        return [
            'pn' => $pn,
            'res_type' => $res,
            'res_id' => $id,
            'password' => $password,
            'new_status' => $new_status,
        ];
    }
}
