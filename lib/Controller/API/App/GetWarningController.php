<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Matecat\ICU\MessagePatternComparator;
use Matecat\ICU\MessagePatternValidator;
use Matecat\SubFiltering\Filters\CtrlCharsPlaceHoldToAscii;
use Matecat\SubFiltering\MateCatFilter;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\Jobs\ChunkDao;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentMetadataDao;
use Model\Segments\SegmentOriginalDataDao;
use Model\Translations\WarningDao;
use Utils\LQA\ICUSourceSegmentChecker;
use Utils\LQA\QA;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;
use View\API\V2\Json\QAGlobalWarning;
use View\API\V2\Json\QALocalWarning;

class GetWarningController extends KleinController
{

    use ICUSourceSegmentChecker;

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws NotFoundException
     * @throws EndQueueException
     * @throws AuthenticationError
     * @throws ReQueueException
     * @throws ValidationError
     * @throws Exception
     */
    public function global(): void
    {
        $request = $this->validateTheGlobalRequest();
        $id_job = $request['id_job'];
        $password = $request['password'];

        try {
            $chunk = $this->getChunk($id_job, $password);
            $warnings = WarningDao::getWarningsByJobIdAndPassword($id_job, $password);
            $tMismatch = (new SegmentDao())->setCacheTTL(10 * 60 /* 10-minute cache */)->getTranslationsMismatches($id_job, $password);

            $qa = new QAGlobalWarning($warnings, $tMismatch);

            $result = array_merge(
                [
                    'data' => [],
                    'errors' => [],
                ],
                $qa->render()
            );

            $result = $this->featureSet->filter('filterGlobalWarnings', $result, [
                'chunk' => $chunk,
            ]);

            $this->response->json($result);
        } catch (Exception) {
            $this->response->json([
                'details' => []
            ]);
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    private function validateTheGlobalRequest(): array
    {
        $id_job = filter_var($this->request->param('id_job'), FILTER_SANITIZE_NUMBER_INT);
        $password = filter_var($this->request->param('password'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);

        if (empty($id_job)) {
            throw new InvalidArgumentException("Empty id job", -1);
        }

        if (empty($password)) {
            throw new InvalidArgumentException("Empty job password", -2);
        }

        return [
            'id_job' => $id_job,
            'password' => $password,
        ];
    }

    /**
     * @return void
     * @throws Exception
     */
    public function local(): void
    {
        $request = $this->validateTheLocalRequest();
        $id = $request['id'];
        $id_job = $request['id_job'];
        $src_content = $request['src_content'];
        $trg_content = $request['trg_content'];
        $password = $request['password'];
        $characters_counter = $request['characters_counter'];

        $chunk = $this->getChunk($id_job, $password);
        $featureSet = $this->getFeatureSet();
        $metadata = new MetadataDao();
        $dataRefMap = (!empty($id)) ? SegmentOriginalDataDao::getSegmentDataRefMap($id) : [];

        // Check if ICU MessageFormat support is enabled for this project (cached for 24 hours)
        // Detect if the translation content contains ICU MessageFormat syntax
        $this->sourceContainsIcu($chunk->getProject(), $chunk, $src_content);

        /** @var MateCatFilter $Filter */
        $Filter = MateCatFilter::getInstance(
            $featureSet,
            $chunk->source,
            $chunk->target,
            $dataRefMap,
            $metadata->getSubfilteringCustomHandlers($chunk->id, $password),
            $this->sourceContainsIcu
        );

        $src_content = $Filter->fromLayer2ToLayer1($src_content);
        $trg_content = $Filter->fromLayer2ToLayer1($trg_content);

        $QA = new QA(
            $src_content,
            $trg_content,
            MessagePatternComparator::fromValidators(
                $this->icuSourcePatternValidator,
                new MessagePatternValidator(
                    $chunk->target,
                    // Transform target content: convert control character placeholders back to ASCII control characters
                    (new CtrlCharsPlaceHoldToAscii())->transform($request['trg_content'])
                )
            ),
            // ICU syntax is enabled for this project, and the translation content must contain valid ICU syntax
            $this->sourceContainsIcu
        );
        $QA->setFeatureSet($featureSet);
        $QA->setChunk($chunk);
        $QA->setSourceSegLang($chunk->source);
        $QA->setTargetSegLang($chunk->target);

        if (!$this->sourceContainsIcu && isset($characters_counter)) {
            $QA->setCharactersCount($characters_counter, SegmentMetadataDao::get($id, QA::SIZE_RESTRICTION)[0] ?? null);
        }

        $QA->performConsistencyCheck();

        $result = array_merge(
            [
                'data' => [],
                'errors' => []
            ],
            $this->invokeLocalWarningsOnFeatures($chunk, $src_content, $trg_content),
            (new QALocalWarning(
                $QA,
                $id,
                $chunk->id_project,
                $Filter
            ))->render()
        );

        $this->response->json($result);
    }

    /**
     * @return array
     * @throws Exception
     */
    private function validateTheLocalRequest(): array
    {
        $id = (int)filter_var($this->request->param('id'), FILTER_SANITIZE_NUMBER_INT, ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR]);
        $id_job = filter_var($this->request->param('id_job'), FILTER_SANITIZE_NUMBER_INT);
        $src_content = filter_var($this->request->param('src_content'), FILTER_UNSAFE_RAW);
        $trg_content = filter_var($this->request->param('trg_content'), FILTER_UNSAFE_RAW);
        $password = filter_var($this->request->param('password'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $token = filter_var($this->request->param('token'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);
        $logs = filter_var($this->request->param('logs'), FILTER_UNSAFE_RAW);
        $segment_status = filter_var($this->request->param('segment_status'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $characters_counter = filter_var($this->request->param('characters_counter'), FILTER_SANITIZE_NUMBER_INT);

        if (empty($id_job)) {
            throw new InvalidArgumentException("Empty id job", -1);
        }

        if (empty($password)) {
            throw new InvalidArgumentException("Empty job password", -2);
        }

        /**
         * Update 2015/08/11, roberto@translated.net
         * getWarning needs the segment status too because of a bug:
         *   sometimes the client calls getWarning and sends an empty trg_content
         *   because the suggestion has not been loaded yet.
         *   This happens only if segment is in status NEW
         */
        if (empty($segment_status)) {
            $segment_status = 'draft';
        }

        return [
            'id' => $id,
            'id_job' => $id_job,
            'src_content' => $src_content,
            'trg_content' => $trg_content,
            'password' => $password,
            'token' => $token,
            'logs' => $logs,
            'segment_status' => $segment_status,
            'characters_counter' => $characters_counter,
        ];
    }

    /**
     * @param $id_job
     * @param $password
     *
     * @return JobStruct|null
     * @throws Exception
     */
    private function getChunk($id_job, $password): ?JobStruct
    {
        $chunk = ChunkDao::getByIdAndPassword($id_job, $password);
        $project = $chunk->getProject();
        $this->featureSet->loadForProject($project);

        return $chunk;
    }

    /**
     * @param JobStruct $chunk
     * @param                $src_content
     * @param                $trg_content
     *
     * @return array
     * @throws Exception
     */
    private function invokeLocalWarningsOnFeatures(JobStruct $chunk, $src_content, $trg_content): array
    {
        $data = [];
        $data = $this->featureSet->filter('filterSegmentWarnings', $data, [
            'src_content' => $src_content,
            'trg_content' => $trg_content,
            'project' => $chunk->getProject(),
            'chunk' => $chunk
        ]);

        return [
            'data' => $data
        ];
    }
}
