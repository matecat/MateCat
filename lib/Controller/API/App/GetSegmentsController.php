<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\LoginValidator;
use DomainException;
use Exception;
use InvalidArgumentException;
use Matecat\ICU\MessagePatternValidator;
use Matecat\Locales\Languages;
use Matecat\SubFiltering\MateCatFilter;
use Model\Conversion\ZipArchiveHandler;
use Model\Exceptions\ValidationError;
use Model\FeaturesBase\Hook\Event\Filter\FilterGetSegmentsResultEvent;
use Model\FeaturesBase\Hook\Event\Filter\PrepareNotesForRenderingEvent;
use Model\Files\FilesMetadataMarshaller;
use Model\Files\MetadataDao as FilesMetadataDao;
use Model\Jobs\JobDao;
use Model\Jobs\MetadataDao;
use Model\Projects\MetadataDao as ProjectMetadataDao;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\Segments\ContextGroupDao;
use Model\Segments\ContextStruct;
use Model\Segments\ContextUrlResolver;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentMetadataCollection;
use Model\Segments\SegmentMetadataDao;
use Model\Segments\SegmentNoteDao;
use Model\Segments\SegmentUIStruct;
use PDOException;
use ReflectionException;
use RuntimeException;
use Utils\LQA\ICUSourceSegmentDetector;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;
use Utils\Tools\CatUtils;

class GetSegmentsController extends KleinController
{

    const int DEFAULT_PER_PAGE = 40;
    const int MAX_PER_PAGE = 200;

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws AuthenticationError
     * @throws ReQueueException
     * @throws ValidationError
     * @throws \Model\Exceptions\NotFoundException
     * @throws EndQueueException
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws DomainException
     * @throws PDOException
     */
    public function segments(): void
    {
        $request = $this->validateTheRequest();
        $jid = $request['jid'];
        $step = $request['step'];
        $id_segment = $request['id_segment'];
        $password = $request['password'];
        $where = $request['where'];

        $job = $this->findJob($jid, $password);

        $project = $job->getProject();
        $projectId = $project->id ?? throw new RuntimeException('Project ID is null');
        $jobId = $job->id ?? throw new RuntimeException('Job ID is null');
        $jobPassword = $job->password ?? throw new RuntimeException('Job password is null');

        $featureSet = $this->getFeatureSet();
        $featureSet->loadForProject($project);
        $lang_handler = Languages::getInstance();

        $parsedIdSegment = $this->parseIdSegment($id_segment);

        if ($parsedIdSegment['id_segment'] == '') {
            $parsedIdSegment['id_segment'] = 0;
        }

        $sDao = $this->createSegmentDao();
        $data = $sDao->getPaginationSegments(
            $job,
            min($step, self::DEFAULT_PER_PAGE),
            (int) $parsedIdSegment['id_segment'],
            $where,
            [
                'optional_fields' => [
                    'st.edit_distance',
                    'st.version_number'
                ]
            ]
        );

        $segment_notes = $this->prepareNotes($data);
        $contexts = $this->getContextGroups($data);
        $res = [];

        $projectMetadata = $this->createProjectMetadataDao();
        $icu_enabled = $projectMetadata->setCacheTTL(60 * 60 * 24)->get($projectId, ProjectsMetadataMarshaller::ICU_ENABLED->value)->value ?? false;

        $projectContextUrl = $projectMetadata->setCacheTTL(60 * 60 * 24)->get(
            $projectId,
            ProjectsMetadataMarshaller::CONTEXT_URL->value
        )?->value;

        $filesMetadataDao = $this->createFilesMetadataDao();
        $fileContextUrls = [];

        $segmentMetadataMap = [];
        if (!empty($data)) {
            $start = (int)$data[0]['sid'];
            $last = end($data);
            $stop = (int)$last['sid'];
            $segmentMetadataMap = $this->createSegmentMetadataDao()->getAllInRange($start, $stop);
        }

        foreach ($data as $seg) {
            $id_file = $seg['id_file'];

            if (!isset($res[$id_file])) {
                $res[$id_file]['jid'] = $seg['jid'];
                $res[$id_file]["filename"] = ZipArchiveHandler::getFileName($seg['filename']);
                $res[$id_file]['source'] = $lang_handler->getLocalizedName($job->source);
                $res[$id_file]['target'] = $lang_handler->getLocalizedName($job->target);
                $res[$id_file]['source_code'] = $job->source;
                $res[$id_file]['target_code'] = $job->target;
                $res[$id_file]['segments'] = [];

                $fileContextUrls[$id_file] = $filesMetadataDao->setCacheTTL(60 * 60 * 24)->get(
                    $projectId,
                    $id_file,
                    FilesMetadataMarshaller::CONTEXT_URL->value
                )?->value;
            }

            if (isset($seg['edit_distance'])) {
                $seg['edit_distance'] = round($seg['edit_distance'] / 1000, 2);
            } else {
                $seg['edit_distance'] = 0;
            }

            $seg['parsed_time_to_edit'] = CatUtils::parse_time_to_edit($seg['time_to_edit']);

            ($seg['source_chunk_lengths'] === null ? $seg['source_chunk_lengths'] = '[]' : null);
            ($seg['target_chunk_lengths'] === null ? $seg['target_chunk_lengths'] = '{"len":[0],"statuses":["DRAFT"]}' : null);
            $seg['source_chunk_lengths'] = json_decode($seg['source_chunk_lengths'], true);
            $seg['target_chunk_lengths'] = json_decode($seg['target_chunk_lengths'], true);

            // inject original data ref map (FOR XLIFF 2.0)
            $data_ref_map = json_decode($seg['data_ref_map'] ?? '', true);
            $seg['data_ref_map'] = $data_ref_map;

            $string_contains_icu = false;
            if ($icu_enabled) {
                $analyzer = new MessagePatternValidator(
                    language: $job->source,
                    patternString: $seg['segment']
                );
                $string_contains_icu = ICUSourceSegmentDetector::sourceContainsIcu($analyzer, $icu_enabled);
            }

            $jobMetadata = $this->createJobMetadataDao();
            $Filter = MateCatFilter::getInstance(
                $featureSet,
                $job->source,
                $job->target,
                $data_ref_map ?? [],
                $jobMetadata->getSubfilteringCustomHandlers($jobId, $jobPassword),
                $string_contains_icu
            );

            if (!$Filter instanceof MateCatFilter) {
                throw new RuntimeException('Expected MateCatFilter instance');
            }

            $seg['icu'] = $string_contains_icu;

            $seg['segment'] = $Filter->fromLayer0ToLayer1(
                CatUtils::reApplySegmentSplit($seg['segment'], $seg['source_chunk_lengths']) ?? ''
            );

            $seg['translation'] = $Filter->fromLayer0ToLayer1(
            // When the query for segments is performed, a condition is added to get NULL instead of the translation when the status is NEW
                CatUtils::reApplySegmentSplit($seg['translation'], $seg['target_chunk_lengths']['len']) ?? ''
            );

            $seg['translation'] = $Filter->fromLayer1ToLayer2($Filter->realignIDInLayer1($seg['segment'], $seg['translation']));
            $seg['segment'] = $Filter->fromLayer1ToLayer2($seg['segment']);

            $segmentMetadata = $segmentMetadataMap[(int)$seg['sid']] ?? new SegmentMetadataCollection([]);
            $seg['metadata'] = $segmentMetadata->jsonSerialize();
            $seg['context_url'] = ContextUrlResolver::resolve(
                $segmentMetadata,
                $fileContextUrls[$id_file] ?? null,
                $projectContextUrl
            );

            $this->attachNotes($seg, $segment_notes);
            $this->attachContexts($seg, $contexts);

            $res[$id_file]['segments'][] = $seg;
        }

        $result = [
            'errors' => [],
        ];

        $result['data']['files'] = $res;
        $result['data']['where'] = $where;
        $filterGetSegmentsResultEvent = new FilterGetSegmentsResultEvent($result['data'], $job);
        $featureSet->dispatch($filterGetSegmentsResultEvent);
        $result['data'] = $filterGetSegmentsResultEvent->getData();

        $this->response->json($result);
    }

    /**
     * @return array{jid: int, id_segment: string, password: string, where: ?string, step: int}
     *
     * @throws InvalidArgumentException
     */
    protected function validateTheRequest(): array
    {
        $jid = filter_var($this->request->param('jid'), FILTER_SANITIZE_NUMBER_INT);
        $step = filter_var($this->request->param('step'), FILTER_SANITIZE_NUMBER_INT);
        $id_segment = filter_var($this->request->param('segment'), FILTER_SANITIZE_NUMBER_INT);
        $password = filter_var($this->request->param('password'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW]);
        $where = filter_var($this->request->param('where'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW]);

        if (empty($jid)) {
            throw new InvalidArgumentException("No id job provided", -1);
        }

        if (empty($password)) {
            throw new InvalidArgumentException("No job password provided", -2);
        }

        if (empty($id_segment)) {
            throw new InvalidArgumentException("No is segment provided", -3);
        }

        if ($step > self::MAX_PER_PAGE) {
            $step = self::MAX_PER_PAGE;
        }

        return [
            'jid' => (int) $jid,
            'id_segment' => $id_segment,
            'password' => $password,
            'where' => $where ?: null,
            'step' => (int) $step,
        ];
    }

    /**
     * @param SegmentUIStruct $segment
     * @param array<int, list<array<string, int|string>>> $segment_notes
     *
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws ValidationError
     * @throws \Model\Exceptions\NotFoundException
     * @throws DomainException
     */
    private function attachNotes(SegmentUIStruct &$segment, array $segment_notes): void
    {
        $notes = $segment_notes[(int)$segment['sid']] ?? null;

        if (is_array($notes)) {
            $prepareNotesForRenderingEvent = new PrepareNotesForRenderingEvent($notes);
            $this->featureSet->dispatch($prepareNotesForRenderingEvent);
            $notes = $prepareNotesForRenderingEvent->getNotes();
        }

        $segment['notes'] = $notes;
    }

    /**
     * @param SegmentUIStruct $segment
     * @param array<int, ContextStruct> $contexts
     *
     * @throws DomainException
     */
    private function attachContexts(SegmentUIStruct &$segment, array $contexts): void
    {
        $segment['context_groups'] = $contexts[(int)$segment['sid']] ?? null;
    }

    /**
     * @param SegmentUIStruct[] $segments
     *
     * @return array<int, list<array<string, int|string>>>
     *
     * @throws PDOException
     * @throws DomainException
     */
    protected function prepareNotes(array $segments): array
    {
        if (!empty($segments[0])) {
            $start = $segments[0]['sid'];
            $last = end($segments);
            $stop = $last['sid'];

            return SegmentNoteDao::getAggregatedBySegmentIdInInterval($start, $stop);
        }

        return [];
    }

    /**
     * @param SegmentUIStruct[] $segments
     *
     * @return array<int, ContextStruct>
     *
     * @throws ReflectionException
     * @throws Exception
     */
    protected function getContextGroups(array $segments): array
    {
        if (!empty($segments[0])) {
            $start = $segments[0]['sid'];
            $last = end($segments);
            $stop = $last['sid'];

            return (new ContextGroupDao())->getBySIDRange($start, $stop);
        }

        return [];
    }

    /**
     * @throws AuthenticationError
     * @throws NotFoundException
     * @throws Exception
     */
    protected function findJob(int $jid, string $password): \Model\Jobs\JobStruct
    {
        return (new JobDao())->getByIdAndPasswordOrFail($jid, $password);
    }

    protected function createSegmentDao(): SegmentDao
    {
        return new SegmentDao();
    }

    protected function createProjectMetadataDao(): ProjectMetadataDao
    {
        return new ProjectMetadataDao();
    }

    protected function createFilesMetadataDao(): FilesMetadataDao
    {
        return new FilesMetadataDao();
    }

    protected function createJobMetadataDao(): MetadataDao
    {
        return new MetadataDao();
    }

    protected function createSegmentMetadataDao(): SegmentMetadataDao
    {
        return new SegmentMetadataDao();
    }
}
