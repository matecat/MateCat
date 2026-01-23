<?php

namespace View\API\V2\Json;

use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\IDaoStruct;
use Model\LQA\EntryCommentDao;
use Model\LQA\EntryStruct;
use Plugins\Features\ReviewExtended\ReviewUtils;
use SplFileObject;

class SegmentTranslationIssue
{

    /**
     * @var SplFileObject
     */
    private SplFileObject $csvHandler;

    public function __construct()
    {
    }

    public function renderItem(IdaoStruct $record): array
    {
        $dao = new EntryCommentDao();
        /** @var EntryStruct $record */
        $comments = $dao->findByIssueId($record->id);
        $record = new EntryStruct($record->getArrayCopy());

        return [
            'uid' => $record->uid,
            'comment' => $record->comment,
            'created_at' => date('c', strtotime($record->create_date ?? 'now')),
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

    public function genCSVTmpFile($data): string
    {
        $filePath = tempnam("/tmp", "SegmentsIssuesComments_");
        $csvHandler = new SplFileObject($filePath, "w");
        $csvHandler->setCsvControl(';');

        $this->csvHandler = $csvHandler; // set the handler to allow to clean resource

        $csv_fields = [
            "ID Segment",
            "Category",
            "Severity",
            "Selected Text",
            "Message",
            "Created At",
        ];

        $csvHandler->fputcsv($csv_fields);

        foreach ($data as $record) {
            $dao = new EntryCommentDao();

            $comments = $dao->findByIssueId($record->id);
            foreach ($comments as $c) {
                $combined = array_combine($csv_fields, array_fill(0, count($csv_fields), ''));

                $combined["ID Segment"] = $record->id_segment;
                $combined["Category"] = $record->category_label;
                $combined["Severity"] = $record->severity;
                $combined["Selected Text"] = $record->target_text;
                $combined["Message"] = $c->comment;
                $combined["Created At"] = $this->getDateValue($c->create_date);
                $csvHandler->fputcsv($combined);
            }
        }

        return $filePath;
    }

    /**
     * Render an array of records into a JSON format.
     *
     * @param AbstractDaoObjectStruct[] $array
     *
     * @return array
     */
    public function render(array $array): array
    {
        $out = [];

        foreach ($array as $record) {
            $out[] = $this->renderItem($record);
        }

        return $out;
    }

    private function getDateValue(?string $strDate = null): ?string
    {
        if ($strDate != null) {
            return date('c', strtotime($strDate));
        }

        return null;
    }

    public function cleanDownloadResource(): void
    {
        $path = $this->csvHandler->getRealPath();
        unset($this->csvHandler);
        @unlink($path);
    }

}
