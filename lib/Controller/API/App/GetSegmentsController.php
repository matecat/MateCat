<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Matecat\ICU\MessagePattern;
use Matecat\SubFiltering\MateCatFilter;
use Model\Conversion\ZipArchiveHandler;
use Model\Exceptions\ValidationError;
use Model\Jobs\ChunkDao;
use Model\Jobs\MetadataDao;
use Model\Projects\MetadataDao as ProjectMetadataDao;
use Model\Segments\ContextGroupDao;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentMetadataDao;
use Model\Segments\SegmentNoteDao;
use Model\Segments\SegmentUIStruct;
use ReflectionException;
use Utils\Langs\Languages;
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
     */
    public function segments(): void
    {
        $request = $this->validateTheRequest();
        $jid = $request['jid'];
        $step = $request['step'];
        $id_segment = $request['id_segment'];
        $password = $request['password'];
        $where = $request['where'];

        $job = ChunkDao::getByIdAndPassword($jid, $password);

        $project = $job->getProject();
        $featureSet = $this->getFeatureSet();
        $featureSet->loadForProject($project);
        $lang_handler = Languages::getInstance();

        $parsedIdSegment = $this->parseIDSegment($id_segment);

        if ($parsedIdSegment['id_segment'] == '') {
            $parsedIdSegment['id_segment'] = 0;
        }

        $sDao = new SegmentDao();
        $data = $sDao->getPaginationSegments(
            $job,
            min($step, self::DEFAULT_PER_PAGE),
            $parsedIdSegment['id_segment'],
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

        $projectMetadata = new ProjectMetadataDao();
        $icu_enabled = $projectMetadata->setCacheTTL(60 * 60 * 24)->get($job->id, ProjectMetadataDao::ICU_ENABLED)?->value ?? false;

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

            if ($icu_enabled) {
                $pattern = new MessagePattern($seg['segment']);
                $string_contains_icu = $pattern->countParts() > 2;
            }

            /** @var MateCatFilter $Filter */
            $jobMetadata = new MetadataDao();
            $Filter = MateCatFilter::getInstance(
                $featureSet,
                $job->source,
                $job->target,
                null !== $data_ref_map ? $data_ref_map : [],
                $jobMetadata->getSubfilteringCustomHandlers($job->id, $job->password),
                $string_contains_icu ?? false
            );


            $seg['segment'] = $Filter->fromLayer0ToLayer1(
                CatUtils::reApplySegmentSplit($seg['segment'], $seg['source_chunk_lengths'])
            );

            $seg['translation'] = $Filter->fromLayer0ToLayer1(
            // When the query for segments is performed, a condition is added to get NULL instead of the translation when the status is NEW
                CatUtils::reApplySegmentSplit($seg['translation'], $seg['target_chunk_lengths']['len']) ?? ''  // use the null coalescing operator
            );

            $seg['translation'] = $Filter->fromLayer1ToLayer2($Filter->realignIDInLayer1($seg['segment'], $seg['translation']));
            $seg['segment'] = $Filter->fromLayer1ToLayer2($seg['segment']);

            $seg['metadata'] = SegmentMetadataDao::getAll($seg['sid']);

            $this->attachNotes($seg, $segment_notes);
            $this->attachContexts($seg, $contexts);

            $res[$id_file]['segments'][] = $seg;
        }

        $result = [
            'errors' => [],
        ];

        $result['data']['files'] = $res;
        $result['data']['where'] = $where;
        $result['data'] = $featureSet->filter('filterGetSegmentsResult', $result['data'], $job);

        $this->response->json($result);
    }

    /**
     * @return array
     */
    private function validateTheRequest(): array
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
            'jid' => $jid,
            'id_segment' => $id_segment,
            'password' => $password,
            'where' => $where,
            'step' => $step,
        ];
    }

    /**
     * @param SegmentUIStruct $segment
     * @param array $segment_notes
     *
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws ValidationError
     * @throws \Model\Exceptions\NotFoundException
     */
    private function attachNotes(SegmentUIStruct &$segment, array $segment_notes): void
    {
        $notes = $segment_notes[(int)$segment['sid']] ?? null;

        if (is_array($notes)) {
            $notes = $this->featureSet->filter('prepareNotesForRendering', $notes);
        }

        $segment['notes'] = $notes;
    }

    /**
     * @param SegmentUIStruct $segment
     * @param array $contexts
     */
    private function attachContexts(SegmentUIStruct &$segment, array $contexts): void
    {
        $segment['context_groups'] = $contexts[(int)$segment['sid']] ?? null;
    }

    /**
     * @param $segments
     *
     * @return array
     * @throws AuthenticationError
     * @throws \Model\Exceptions\NotFoundException
     * @throws ValidationError
     * @throws EndQueueException
     * @throws ReQueueException
     */
    private function prepareNotes($segments): array
    {
        if (!empty($segments[0])) {
            $start = $segments[0]['sid'];
            $last = end($segments);
            $stop = $last['sid'];

            if ($this->featureSet->filter('prepareAllNotes', false)) {
                $segment_notes = SegmentNoteDao::getAllAggregatedBySegmentIdInInterval($start, $stop);
                foreach ($segment_notes as $k => $noteObj) {
                    $segment_notes[$k][0]['json'] = json_decode($noteObj[0]['json'], true);
                }

                return $this->featureSet->filter('processExtractedJsonNotes', $segment_notes);
            }

            return SegmentNoteDao::getAggregatedBySegmentIdInInterval($start, $stop);
        }

        return [];
    }

    /**
     * @param $segments
     *
     * @return array
     * @throws ReflectionException
     */
    private function getContextGroups($segments): array
    {
        if (!empty($segments[0])) {
            $start = $segments[0]['sid'];
            $last = end($segments);
            $stop = $last['sid'];

            return (new ContextGroupDao())->getBySIDRange($start, $stop);
        }

        return [];
    }
}