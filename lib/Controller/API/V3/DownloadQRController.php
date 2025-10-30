<?php

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use DOMDocument;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use Model\Jobs\ChunkDao;
use Model\Jobs\JobStruct;
use Model\QualityReport\QualityReportSegmentModel;
use ZipArchive;


class DownloadQRController extends KleinController
{

    /**
     * @var string|null
     */
    private ?string $format;

    /**
     * @var int
     */
    private int $segmentsPerFile;

    /**
     * @var array
     */
    private array $allowedFormats = ['csv', 'json', 'xml'];

    /**
     * Download QR to a file
     * @throws Exception
     */
    public function download(): void
    {
        $idJob                 = $this->request->param('jid');
        $password              = $this->request->param('password');
        $this->format          = $this->request->param('format', 'csv');
        $this->segmentsPerFile = $this->request->param('segmentsPerFile', 20);

        if ($this->segmentsPerFile > 100) {
            $this->segmentsPerFile = 100;
        }

        if (!in_array($this->format, $this->allowedFormats)) {
            throw new AuthorizationError('Invalid format. Allowed formats are [' . implode(', ', $this->allowedFormats) . ']');
        }

        $chunk = ChunkDao::getByIdAndPassword($idJob, $password);

        $prefix   = "QR_" . $idJob . "_" . $password . "_";
        $filePath = tempnam("/tmp", $prefix);

        $fileContent = $this->composeFileContent($chunk);
        file_put_contents($filePath, $fileContent);
        $this->downloadFile($this->fileMimeType(), $prefix . date('YmdHis') . '.' . $this->format, $filePath);
    }

    /**
     * @return string
     */
    private function fileMimeType(): string
    {
        if ($this->format === 'json') {
            return 'application/json';
        }

        if ($this->format === 'csv') {
            return 'text/csv';
        }

        if ($this->format === 'xml') {
            return 'text/xml';
        }

        return 'application/octet-stream';
    }

    /**
     * @param JobStruct $chunk
     *
     * @return bool|false|string
     * @throws Exception
     */
    private function composeFileContent(JobStruct $chunk): bool|string
    {
        $data = [];

        $qrSegmentModel = new QualityReportSegmentModel($chunk);

        // categories issues
        $project    = $chunk->getProject();
        $model      = $project->getLqaModel();
        $categories = $model !== null ? $model->getCategoriesAndSeverities() : [];

        $categoryIssues = [];

        foreach ($categories as $category) {
            foreach ($category[ 'severities' ] as $severity) {
                $categoryIssues[] = $category[ 'label' ] . ' [' . $severity[ 'label' ] . ']';
            }
        }

        $ids = [];
        $this->buildArrayOfSegmentIds($qrSegmentModel, $this->segmentsPerFile, 0, $ids);

        // merge all data here
        foreach ($ids as $segments_ids) {
            $data = array_merge($data, $this->buildFileContentFromArrayOfSegmentIds($qrSegmentModel, $segments_ids));
        }

        // compose a unique file
        if ($this->format === 'json') {
            $uniqueFile = $this->createJsonFile($data, $categoryIssues);
        }

        if ($this->format === 'csv') {
            $uniqueFile = $this->createCSVFile($data, $categoryIssues);
        }

        if ($this->format === 'xml') {
            $uniqueFile = $this->createXMLFile($data, $categoryIssues);
        }

        if (!isset($uniqueFile)) {
            throw new Exception('Merging files for download failed.');
        }

        return $uniqueFile;
    }

    /**
     * @param QualityReportSegmentModel $qrSegmentModel
     * @param int                       $step
     * @param int                       $refSegment
     * @param array                     $ids
     *
     * @return void
     * @throws Exception
     */
    private function buildArrayOfSegmentIds(QualityReportSegmentModel $qrSegmentModel, int $step, int $refSegment, array &$ids): void
    {
        $where  = "after";
        $filter = ['filter' => null];

        $segments_ids = $qrSegmentModel->getSegmentsIdForQR($step, $refSegment, $where, $filter);

        if (!empty($segments_ids)) {
            $refSegment = end($segments_ids);
            $ids[]      = $segments_ids;
            $this->buildArrayOfSegmentIds($qrSegmentModel, $step, $refSegment, $ids);
        }
    }

    /**
     * @param QualityReportSegmentModel $qrSegmentModel
     * @param                           $segments_ids
     *
     * @return array
     * @throws Exception
     */
    private function buildFileContentFromArrayOfSegmentIds(QualityReportSegmentModel $qrSegmentModel, $segments_ids): array
    {
        $segments = $qrSegmentModel->getSegmentsForQR($segments_ids);

        $data = [];

        foreach ($segments as $segment) {
            $issues   = [];
            $comments = [];

            foreach ($segment->issues as $issue) {
                $label = $issue->issue_category . ' [' . $issue->issue_severity . ']';

                if (!isset($issues[ $label ])) {
                    $issues[ $label ] = 0;
                }

                $issues[ $label ] = $issues[ $label ] + 1;

                foreach ($issue->comments ?? [] as $comment) {
                    $comments[ $label ][] = $comment;
                }
            }

            $data[] = [
                    $segment->sid,
                    $segment->target,
                    $segment->segment,
                    $segment->raw_word_count,
                    $segment->translation,
                    $segment->version,
                    $segment->ice_locked,
                    $segment->status,
                    $segment->time_to_edit,
                    $segment->filename,
                    $segment->id_file,
                    $segment->warning,
                    $segment->suggestion_match,
                    $segment->suggestion_source,
                    $segment->suggestion,
                    $segment->edit_distance,
                    $segment->locked,
                    $segment->match_type,
                    $segment->pee,
                    $segment->ice_modified,
                    $segment->secs_per_word,
                    $segment->parsed_time_to_edit[ 0 ] . ":" . $segment->parsed_time_to_edit[ 1 ] . ":" . $segment->parsed_time_to_edit[ 2 ] . "." . $segment->parsed_time_to_edit[ 3 ],
                    $segment->last_translation,
                    (!empty($segment->last_revisions) and isset($segment->last_revisions[ 0 ])) ? $segment->last_revisions[ 0 ][ 'translation' ] : null,
                    (!empty($segment->last_revisions) and isset($segment->last_revisions[ 1 ])) ? $segment->last_revisions[ 1 ][ 'translation' ] : null,
                    $segment->pee_translation_revise,
                    $segment->pee_translation_suggestion,
                    $segment->version_number,
                    $segment->source_page,
                    $segment->is_pre_translated,
                    $issues,
                    $comments,
            ];
        }

        return $data;
    }

    /**
     * @param array $data
     * @param array $categoryIssues
     *
     * @return bool|false|string
     */
    private function createCSVFile(array $data, array $categoryIssues = []): bool|string
    {
        $headings = [
                "sid",
                "target",
                "segment",
                "raw_word_count",
                "translation",
                "version",
                "ice_locked",
                "status",
                "time_to_edit",
                "filename",
                "id_file",
                "warning",
                "suggestion_match",
                "suggestion_source",
                "suggestion",
                "edit_distance",
                "locked",
                "match_type",
                "pee",
                "ice_modified",
                "secs_per_word",
                "parsed_time_to_edit",
                "last_translation",
                "revision",
                "second_pass_revision",
                "pee_translation_revise",
                "pee_translation_suggestion",
                "version_number",
                "source_page",
                "is_pre_translated",
        ];

        foreach ($categoryIssues as $categoryIssue) {
            $headings[] = $categoryIssue;
            $headings[] = "comments";
        }

        $csvData   = [];
        $csvData[] = $headings;

        foreach ($data as $datum) {
            // comments
            $comments = $datum[ 31 ];

            // issues
            $issues = $datum[ 30 ];
            unset($datum[ 30 ]);
            unset($datum[ 31 ]);

            foreach ($categoryIssues as $categoryIssue) {
                $count         = (isset($issues[ $categoryIssue ])) ? $issues[ $categoryIssue ] : 0;
                $issueComments = [];

                if (isset($comments[ $categoryIssue ])) {
                    foreach ($comments[ $categoryIssue ] as $issueComment) {
                        $issueComments[] = $issueComment[ 'comment' ];
                    }
                }

                $datum[] = $count;
                $datum[] = implode("|||", $issueComments);
            }

            $csvData[] = array_values($datum);
        }

        $tmpFilePath = tempnam("/tmp", '');

        $fp = fopen($tmpFilePath, 'w');
        foreach ($csvData as $fields) {
            if (!fputcsv($fp, $fields)) {
                return false;
            }
        }
        fclose($fp);

        $fileContent = file_get_contents($tmpFilePath);
        unlink($tmpFilePath);

        return $fileContent;
    }

    private function createXMLFile(array $data, array $categoryIssues = []): false|string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<segments>';

        foreach ($data as $datum) {
            // comments
            $comments = $datum[ 31 ];

            // issues
            $issues = $datum[ 30 ];
            unset($datum[ 30 ]);
            $issueValues = [];

            foreach ($categoryIssues as $categoryIssue) {
                $count                         = (isset($issues[ $categoryIssue ])) ? $issues[ $categoryIssue ] : 0;
                $issueValues[ $categoryIssue ] = [
                        'count'    => $count,
                        'comments' => (isset($comments[ $categoryIssue ])) ? $comments[ $categoryIssue ] : [],
                ];
            }

            $xml .= '<segment>';
            $xml .= '<sid>' . $datum[ 0 ] . '</sid>';
            $xml .= '<target>' . $datum[ 1 ] . '</target>';
            $xml .= '<segment>' . $datum[ 2 ] . '</segment>';
            $xml .= '<raw_word_count>' . $datum[ 3 ] . '</raw_word_count>';
            $xml .= '<translation>' . $datum[ 4 ] . '</translation>';
            $xml .= '<version>' . $datum[ 5 ] . '</version>';
            $xml .= '<ice_locked>' . $datum[ 6 ] . '</ice_locked>';
            $xml .= '<status>' . $datum[ 7 ] . '</status>';
            $xml .= '<time_to_edit>' . $datum[ 8 ] . '</time_to_edit>';
            $xml .= '<filename>' . $datum[ 9 ] . '</filename>';
            $xml .= '<id_file>' . $datum[ 10 ] . '</id_file>';
            $xml .= '<warning>' . $datum[ 11 ] . '</warning>';
            $xml .= '<suggestion_match>' . $datum[ 12 ] . '</suggestion_match>';
            $xml .= '<suggestion_source>' . $datum[ 13 ] . '</suggestion_source>';
            $xml .= '<suggestion>' . $datum[ 14 ] . '</suggestion>';
            $xml .= '<edit_distance>' . $datum[ 15 ] . '</edit_distance>';
            $xml .= '<locked>' . $datum[ 16 ] . '</locked>';
            $xml .= '<match_type>' . $datum[ 17 ] . '</match_type>';
            $xml .= '<pee>' . $datum[ 18 ] . '</pee>';
            $xml .= '<ice_modified>' . $datum[ 19 ] . '</ice_modified>';
            $xml .= '<secs_per_word>' . $datum[ 20 ] . '</secs_per_word>';
            $xml .= '<parsed_time_to_edit>' . $datum[ 21 ] . '</parsed_time_to_edit>';
            $xml .= '<last_translation>' . $datum[ 22 ] . '</last_translation>';
            $xml .= '<revision>' . $datum[ 23 ] . '</revision>';
            $xml .= '<second_pass_revision>' . $datum[ 24 ] . '</second_pass_revision>';
            $xml .= '<pee_translation_revise>' . $datum[ 25 ] . '</pee_translation_revise>';
            $xml .= '<pee_translation_suggestion>' . $datum[ 26 ] . '</pee_translation_suggestion>';
            $xml .= '<version_number>' . $datum[ 27 ] . '</version_number>';
            $xml .= '<source_page>' . $datum[ 28 ] . '</source_page>';
            $xml .= '<is_pre_translated>' . $datum[ 29 ] . '</is_pre_translated>';

            //$issueValues
            $xml .= '<issues>';

            foreach ($issueValues as $label => $issueValue) {
                $count    = $issueValue[ 'count' ];
                $comments = $issueValue[ 'comments' ];

                $xml .= '<issue>';
                $xml .= '<label>' . $label . '</label>';
                $xml .= '<count>' . $count . '</count>';
                $xml .= '<comments>';

                if (!empty($comments)) {
                    foreach ($comments as $comment) {
                        $xml .= '<comment>';
                        $xml .= '<id>' . $comment[ 'id' ] . '</id>';
                        $xml .= '<uid>' . $comment[ 'uid' ] . '</uid>';
                        $xml .= '<id_qa_entry>' . $comment[ 'id_qa_entry' ] . '</id_qa_entry>';
                        $xml .= '<create_date>' . $comment[ 'create_date' ] . '</create_date>';
                        $xml .= '<comment>' . $comment[ 'comment' ] . '</comment>';
                        $xml .= '<source_page>' . $comment[ 'source_page' ] . '</source_page>';
                        $xml .= '</comment>';
                    }
                }

                $xml .= '</comments>';
                $xml .= '</issue>';
            }

            $xml .= '</issues>';
            $xml .= '</segment>';
        }

        $xml .= '</segments>';

        $dom                     = new DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml, LIBXML_NOENT);
        $dom->formatOutput = true;

        return $dom->saveXML();
    }

    /**
     * @param array $data
     * @param array $categoryIssues
     *
     * @return false|string
     */
    private function createJsonFile(array $data, array $categoryIssues = []): false|string
    {
        $jsonData = [];

        foreach ($data as $datum) {
            // comments
            $comments = $datum[ 31 ];

            // issues
            $issues = $datum[ 30 ];
            unset($datum[ 30 ]);
            $issueValues = [];

            foreach ($categoryIssues as $categoryIssue) {
                $count                         = (isset($issues[ $categoryIssue ])) ? $issues[ $categoryIssue ] : 0;
                $issueValues[ $categoryIssue ] = [
                        'count'    => $count,
                        'comments' => (isset($comments[ $categoryIssue ])) ? $comments[ $categoryIssue ] : [],
                ];
            }

            $jsonData[] = [
                    "sid"                        => $datum[ 0 ],
                    "target"                     => $datum[ 1 ],
                    "segment"                    => $datum[ 2 ],
                    "raw_word_count"             => $datum[ 3 ],
                    "translation"                => $datum[ 4 ],
                    "version"                    => $datum[ 5 ],
                    "ice_locked"                 => $datum[ 6 ],
                    "status"                     => $datum[ 7 ],
                    "time_to_edit"               => $datum[ 8 ],
                    "filename"                   => $datum[ 9 ],
                    "id_file"                    => $datum[ 10 ],
                    "warning"                    => $datum[ 11 ],
                    "suggestion_match"           => $datum[ 12 ],
                    "suggestion_source"          => $datum[ 13 ],
                    "suggestion"                 => $datum[ 14 ],
                    "edit_distance"              => $datum[ 15 ],
                    "locked"                     => $datum[ 16 ],
                    "match_type"                 => $datum[ 17 ],
                    "pee"                        => $datum[ 18 ],
                    "ice_modified"               => $datum[ 19 ],
                    "secs_per_word"              => $datum[ 20 ],
                    "parsed_time_to_edit"        => $datum[ 21 ],
                    "last_translation"           => $datum[ 22 ],
                    "revision"                   => $datum[ 23 ],
                    "second_pass_revision"       => $datum[ 24 ],
                    "pee_translation_revise"     => $datum[ 25 ],
                    "pee_translation_suggestion" => $datum[ 26 ],
                    "version_number"             => $datum[ 27 ],
                    "source_page"                => $datum[ 28 ],
                    "is_pre_translated"          => $datum[ 29 ],
                    "issues"                     => $issueValues
            ];
        }

        return json_encode($jsonData, JSON_PRETTY_PRINT);
    }

    /**
     * @param string $filename
     * @param array  $files
     */
    private function composeZipFile(string $filename, array $files): void
    {
        $zip = new ZipArchive;

        if ($zip->open($filename, ZipArchive::CREATE)) {
            foreach ($files as $index => $fileContent) {
                $zip->addFromString("qr_file__" . ($index + 1) . "." . $this->format, $fileContent);
            }

            $zip->close();
        }
    }

    /**
     * Download a file
     *
     * @param string $mimeType
     * @param string $filename
     * @param string $filePath
     */
    #[NoReturn]
    private function downloadFile(string $mimeType, string $filename, string $filePath): void
    {
        $outputContent = file_get_contents($filePath);

        ob_get_contents();
        ob_get_clean();
        ob_start("ob_gzhandler");
        header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        header("Content-Type: $mimeType");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Expires: 0");
        header("Connection: close");
        header("Content-Length: " . strlen($outputContent));
        echo $outputContent;
        unlink($filePath);
        exit;
    }
}