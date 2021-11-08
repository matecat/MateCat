<?php

namespace API\V3;

use API\V2\BaseChunkController;
use QualityReport\QualityReportSegmentModel;


class DownloadQRController extends BaseChunkController {

    /**
     * @var int
     */
    private $idJob;

    /**
     * @var string
     */
    private $password;

    /**
     * @var string|null
     */
    private $format;

    /**
     * @var int
     */
    private $segmentsPerFile;

    /**
     * @var array
     */
    private $allowedFormats = ['csv', 'json'];

    /**
     * Download QR to a file
     */
    public function download() {

        $this->idJob = $this->request->param( 'jid' );
        $this->password = $this->request->param( 'password' );
        $this->format = $this->request->param( 'format', 'csv' );
        $this->segmentsPerFile = $this->request->param( 'segmentsPerFile', 20 );

        if ($this->segmentsPerFile > 100) {
            $this->segmentsPerFile = 100;
        }

        if(!in_array($this->format, $this->allowedFormats)){
            $this->response->status()->setCode( 403 );
            $this->response->json( [
                    'errors' => [
                            'code' => 0,
                            'message' => 'Invalid format. Allowed formats are ['.implode($this->allowedFormats, ', ').']'
                    ]
            ] );
            exit();
        }

        try {
            $chunk = \Chunks_ChunkDao::getByIdAndPassword( $this->idJob, $this->password );

            $prefix = "QR_".$this->idJob. "_". $this->password. "_";
            $filePath = tempnam("/tmp", $prefix);

            $files = $this->composeFilesContentArray($chunk);
            $this->composeZipFile($filePath, $files);
            $this->downloadFile('application/zip', $prefix.date('YmdHis').'.zip', $filePath);

        } catch ( \Exceptions\NotFoundException $e ) {
            $this->response->status()->setCode( 404 );
            $this->response->json( [
                    'errors' => [
                            'code' => 0,
                            'message' => $e->getMessage()
                    ]
            ] );
            exit();
        } catch ( \Exception $e ) {
            $this->response->status()->setCode( 500 );
            $this->response->json( [
                    'errors' => [
                            'code' => 0,
                            'message' => $e->getMessage()
                    ]
            ] );
            exit();
        }
    }

    /**
     * @param \Chunks_ChunkStruct $chunk
     *
     * @return array
     * @throws \Exception
     */
    private function composeFilesContentArray( \Chunks_ChunkStruct $chunk) {

        $files = [];

        $qrSegmentModel = new QualityReportSegmentModel( $chunk );

        $ids = [];
        $this->buildArrayOfSegmentIds($qrSegmentModel, $this->segmentsPerFile,0, $ids);

        foreach ( $ids as $segments_ids ) {
            $files[] = $this->buildFileContentFromArrayOfSegmentIds($qrSegmentModel, $segments_ids);
        }

        return  $files;
    }

    /**
     * @param QualityReportSegmentModel $qrSegmentModel
     * @param  int                      $step
     * @param  int                      $refSegment
     * @param  array                    $ids
     *
     * @return array
     * @throws \Exception
     */
    private function buildArrayOfSegmentIds( QualityReportSegmentModel $qrSegmentModel, $step, $refSegment, &$ids) {

        $where = "after";
        $filter = ['filter' => null];

        $segments_ids = $qrSegmentModel->getSegmentsIdForQR( $step, $refSegment, $where, $filter );

        if(!empty($segments_ids)){
            $refSegment = end($segments_ids);
            $ids[] = $segments_ids;
            $this->buildArrayOfSegmentIds($qrSegmentModel, $step, $refSegment, $ids);
        } else {
            return $ids;
        }
    }

    /**
     * @param QualityReportSegmentModel $qrSegmentModel
     * @param                           $segments_ids
     *
     * @return false|string
     * @throws \Exception
     */
    private function buildFileContentFromArrayOfSegmentIds(QualityReportSegmentModel $qrSegmentModel, $segments_ids)
    {
        $segments  = $qrSegmentModel->getSegmentsForQR( $segments_ids );

        $data = [];
        /** @var \QualityReport_QualityReportSegmentStruct $segment */
        foreach ($segments as $segment){

            $issuesCritical = 0;
            $issuesEnhancement = 0;
            $issuesMajor = 0;
            $issuesMinor = 0;
            $issuesNeutral = 0;
            $issuesNone = 0;
            $issuesRepetition = 0;

            foreach ($segment->issues as $issue){
                switch ($issue->issue_severity) {
                    case "Critical":
                        $issuesCritical++;
                        break;

                    case "Enhancement":
                        $issuesEnhancement++;
                        break;

                    case "Major":
                        $issuesMajor++;
                        break;

                    case "Minor":
                        $issuesMinor++;
                        break;

                    case "Neutral":
                        $issuesNeutral++;
                        break;

                    case "None":
                        $issuesNone++;
                        break;

                    case "Repetition":
                        $issuesRepetition++;
                        break;
                }
            }

            $data[] = [
                $segment->sid,
                $segment->target,
                $segment->segment,
                $segment->segment_hash,
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
                $segment->parsed_time_to_edit[0].":".$segment->parsed_time_to_edit[1].":".$segment->parsed_time_to_edit[2].".".$segment->parsed_time_to_edit[3],
                $segment->last_translation,
                (!empty($segment->last_revisions)) ? json_encode($segment->last_revisions) : '',
                $segment->pee_translation_revise,
                $segment->pee_translation_suggestion,
                $segment->version_number,
                $segment->source_page,
                $segment->is_pre_translated,
                $issuesCritical,
                $issuesEnhancement,
                $issuesMajor,
                $issuesMinor,
                $issuesNeutral,
                $issuesNone,
                $issuesRepetition,
            ];
        }

        if($this->format === 'json'){
            return $this->createJsonFile($data);
        }

        if($this->format === 'csv'){
            return $this->createCSVFile($data);
        }
    }

    /**
     * @param array $data
     *
     * @return bool|false|string
     */
    private function createCSVFile(array $data){

        $headings = [
            "sid",
            "target",
            "segment",
            "segment_hash",
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
            "last_revisions",
            "pee_translation_revise",
            "pee_translation_suggestion",
            "version_number",
            "source_page",
            "is_pre_translated",
            "issues_critical",
            "issues_enhancement",
            "issues_major",
            "issues_minor",
            "issues_neutral",
            "issues_none",
            "issues_repetition",
        ];

        $csvData = [];
        $csvData[] = $headings;

        foreach ($data as $datum){
            $csvData[] = $datum;
        }

        $tmpFilePath = tempnam("/tmp", '');

        $fp = fopen( $tmpFilePath, 'w' );
        foreach ( $csvData as $fields ) {
            if ( !fputcsv( $fp, $fields ) ) {
                return false;
            }
        }
        fclose( $fp );

        $fileContent = file_get_contents($tmpFilePath);
        unlink($tmpFilePath);

        return $fileContent;
    }

    /**
     * @param array $data
     *
     * @return false|string
     */
    private function createJsonFile(array $data){

        $jsonData = [];

        foreach ($data as $datum){
            $jsonData[] = [
                "sid" => $datum[0],
                "target" => $datum[1],
                "segment" => $datum[2],
                "segment_hash" => $datum[3],
                "raw_word_count" => $datum[4],
                "translation" => $datum[5],
                "version" => $datum[6],
                "ice_locked" => $datum[7],
                "status" => $datum[8],
                "time_to_edit" => $datum[9],
                "filename" => $datum[10],
                "id_file" => $datum[11],
                "warning" => $datum[12],
                "suggestion_match" => $datum[13],
                "suggestion_source" => $datum[14],
                "suggestion" => $datum[15],
                "edit_distance" => $datum[16],
                "locked" => $datum[17],
                "match_type" => $datum[18],
                "pee" => $datum[19],
                "ice_modified" => $datum[20],
                "secs_per_word" => $datum[21],
                "parsed_time_to_edit" => $datum[22],
                "last_translation" => $datum[23],
                "last_revisions" => $datum[24],
                "pee_translation_revise" => $datum[25],
                "pee_translation_suggestion" => $datum[26],
                "version_number" => $datum[27],
                "source_page" => $datum[28],
                "is_pre_translated" => $datum[29],
                "issues_critical" => $datum[30],
                "issues_enhancement" => $datum[31],
                "issues_major" => $datum[32],
                "issues_minor" => $datum[33],
                "issues_neutral" => $datum[34],
                "issues_none" => $datum[35],
                "issues_repetition" => $datum[36],
            ];
        }


        return json_encode($jsonData, JSON_PRETTY_PRINT);
    }

    /**
     * @param string $filename
     * @param array  $files
     */
    private function composeZipFile($filename, array $files) {
        $zip = new \ZipArchive;

        if ($zip->open($filename, \ZipArchive::CREATE)) {
            foreach ($files as $index => $fileContent){
                $zip->addFromString( "file__".($index+1)."." . $this->format, $fileContent);
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
    private function downloadFile($mimeType, $filename, $filePath) {

        $outputContent = file_get_contents($filePath);

        ob_get_contents();
        ob_get_clean();
        ob_start( "ob_gzhandler" );
        header( "Expires: Tue, 03 Jul 2001 06:00:00 GMT" );
        header( "Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . " GMT" );
        header( "Cache-Control: no-store, no-cache, must-revalidate, max-age=0" );
        header( "Cache-Control: post-check=0, pre-check=0", false );
        header( "Pragma: no-cache" );
        header( "Content-Type: $mimeType" );
        header( "Content-Disposition: attachment; filename=\"$filename\"" );
        header( "Expires: 0" );
        header( "Connection: close" );
        header( "Content-Length: " . strlen( $outputContent ) );
        echo $outputContent;
        unlink($filePath);
        exit;
    }
}