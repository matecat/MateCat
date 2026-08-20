<?php

namespace View\API\V2\Json;

use LogicException;
use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\IDaoStruct;
use Model\LQA\EntryCommentDao;
use Model\LQA\EntryStruct;
use PDOException;
use Plugins\Features\ReviewExtended\ReviewUtils;
use RuntimeException;
use SplFileObject;

class SegmentTranslationIssue
{

    /**
     * @var SplFileObject
     */
    private SplFileObject $csvHandler;

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
     * @param EntryStruct[] $data
     * @throws LogicException
     * @throws RuntimeException
     * @throws PDOException
     */
    public function genCSVTmpFile(array $data): string
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
            $dao = $this->entryCommentDao;

            if ($record->id === null) {
                continue;
            }
            $comments = $dao->findByIssueId($record->id);
            foreach ($comments as $c) {
                $combined = array_combine($csv_fields, array_fill(0, count($csv_fields), ''));

                $combined["ID Segment"] = $record->id_segment;
                $combined["Category"] = self::inertCsvValue($record->category_label);
                $combined["Severity"] = self::inertCsvValue($record->severity);
                $combined["Selected Text"] = self::inertCsvValue($record->target_text);
                $combined["Message"] = self::inertCsvValue($c->comment);
                $combined["Created At"] = $this->getDateValue($c->create_date);
                $csvHandler->fputcsv($combined);
            }
        }

        return $filePath;
    }

    /**
     * Keep a spreadsheet from reading a cell as a formula.
     *
     * Four of the six columns carry text a user typed: the category and severity labels come from
     * whoever authored the QA model, and the selected text and message from whoever raised the
     * issue. Excel, LibreOffice and Google Sheets all evaluate a cell that opens with `=`, `+`, `-`
     * or `@`, so `=cmd|' /C calc'!A1` in a category label is a command that runs when a reviewer
     * opens the report someone sent them. The leading tab and CR are here because a spreadsheet
     * skips them and then reads what follows the same way.
     *
     * A single quote is the prefix every one of those three recognises as "this cell is text". It
     * shows in the formula bar and not in the cell, so a value that needed it still reads correctly.
     *
     * Escaping rather than stripping, because a label may legitimately begin with a minus — a
     * severity called "-2 points" is a label, not an attack — and the reader must still see it.
     */
    private static function inertCsvValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        // `!== 0` rather than `=== 1`: preg_match returns false when PCRE gives up, and this rule
        // decides whether to neutralise a cell. Escaping a value that did not need it costs a
        // leading quote in the formula bar; not escaping one that did runs a command.
        return preg_match('/^[=+\-@\t\r]/', $value) !== 0 ? "'" . $value : $value;
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

    private function getDateValue(?string $strDate = null): ?string
    {
        if ($strDate != null) {
            $timestamp = strtotime($strDate);
            return date('c', $timestamp !== false ? $timestamp : null);
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
