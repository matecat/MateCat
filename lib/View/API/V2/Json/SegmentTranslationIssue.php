<?php

namespace View\API\V2\Json;

use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\IDaoStruct;
use Model\LQA\EntryCommentDao;
use Model\LQA\EntryStruct;
use PDOException;
use Plugins\Features\ReviewExtended\ReviewUtils;
use RuntimeException;

class SegmentTranslationIssue
{

    private EntryCommentDao $entryCommentDao;

    public function __construct(EntryCommentDao $entryCommentDao)
    {
        $this->entryCommentDao = $entryCommentDao;
    }

    /**
     * @return array<string, mixed>
     * @throws RuntimeException
     * @throws PDOException
     */
    public function renderItem(IDaoStruct $record): array
    {
        $dao = $this->entryCommentDao;
        /** @var EntryStruct $record */
        $comments = $dao->findByIssueId($record->id ?? throw new RuntimeException('Missing issue id'));
        $record = new EntryStruct($record->getArrayCopy());
        $timestamp = strtotime($record->create_date ?? 'now');

        return [
            'uid' => $record->uid,
            'comment' => $record->comment,
            'created_at' => date('c', $timestamp !== false ? $timestamp : null),
            'id' => $record->id,
            'id_category' => $record->id_category,
            'id_job' => $record->id_job,
            'id_segment' => $record->id_segment,
            'is_full_segment' => $record->is_full_segment,
            'severity' => $record->severity,
            'start_node' => $record->start_node,
            'start_offset' => $record->start_offset,
            'end_node' => $record->end_node,
            'end_offset' => $record->end_offset,
            'translation_version' => $record->translation_version,
            'target_text' => $record->target_text,
            'penalty_points' => $record->penalty_points,
            'diff' => $record->getDiff(),
            'comments' => $comments,
            'revision_number' => ReviewUtils::sourcePageToRevisionNumber($record->source_page)
        ];
    }

    /**
     * Render an array of records into a JSON format.
     *
     * @param AbstractDaoObjectStruct[] $array
     *
     * @return array<int, array<string, mixed>>
     * @throws RuntimeException
     * @throws PDOException
     */
    public function render(array $array): array
    {
        $out = [];

        foreach ($array as $record) {
            $out[] = $this->renderItem($record);
        }

        return $out;
    }

}
