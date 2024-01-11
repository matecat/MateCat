<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 25/10/19
 * Time: 18:45
 *
 */

namespace API\V3\Json;


use Chunks_ChunkStruct;
use Exception;
use Features\ReviewExtended\Model\QualityReportDao;
use Features\ReviewExtended\ReviewUtils;
use Jobs_JobStruct;
use LQA\ChunkReviewStruct;
use LQA\EntryDao;
use Projects_ProjectStruct;
use Revise\FeedbackDAO;
use RevisionFactory;

class QualitySummary {

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk;
    /**
     * @var Projects_ProjectStruct
     */
    protected $project;

    /**
     * QualitySummary constructor.
     *
     * @param Chunks_ChunkStruct     $chunk
     * @param Projects_ProjectStruct $project
     */
    public function __construct( Chunks_ChunkStruct $chunk, Projects_ProjectStruct $project ) {
        $this->chunk   = $chunk;
        $this->project = $project;
    }

    /**
     * @param ChunkReviewStruct[] $chunkReviewList
     *
     * @return array
     * @throws Exception
     */
    public function render( $chunkReviewList ) {

        $result                      = [];
        $result[ 'quality_summary' ] = [];

        foreach ( $chunkReviewList as $chunkReview ) {
            $result[ 'quality_summary' ][] = $this->renderItem( $chunkReview );
        }

        return $result;
    }

    /**
     * @param ChunkReviewStruct $chunkReview
     *
     * @return mixed
     * @throws Exception
     */
    protected function renderItem( ChunkReviewStruct $chunkReview ) {

        list(
            $passFail,
            $reviseIssues,
            $quality_overall,
            $is_pass,
            $score,
            $total_issues_weight,
            $total_reviewed_words_count,
            $categories,
            $model_version,
            $model_id,
            $model_label,
            $model_template_id
            ) = self::revisionQualityVars( $this->chunk, $this->project, $chunkReview );

        return self::populateQualitySummarySection(
                $chunkReview->source_page,
                $this->chunk,
                $quality_overall,
                $reviseIssues,
                $score,
                $categories,
                $total_issues_weight,
                $total_reviewed_words_count,
                $passFail,
                $chunkReview->total_tte,
                $is_pass,
                $model_version,
                $model_id,
                $model_label,
                $model_template_id,
                $chunkReview->review_password
        );
    }

    /**
     * @param                $chunkReviewPassword
     * @param                $source_page
     * @param Jobs_JobStruct $jStruct
     * @param                $quality_overall
     * @param                $reviseIssues
     * @param                $score
     * @param                $categories
     * @param                $total_issues_weight
     * @param                $total_reviewed_words_count
     * @param                $passfail
     *
     * @param                $total_tte
     * @param                $is_pass
     *
     * @param                $model_version
     * @param                $model_id
     * @param                $model_label
     * @param                $model_template_id
     *
     * @return mixed
     */
    public static function populateQualitySummarySection(
            $source_page,
            Jobs_JobStruct $jStruct,
            $quality_overall,
            $reviseIssues,
            $score,
            $categories,
            $total_issues_weight,
            $total_reviewed_words_count,
            $passfail,
            $total_tte,
            $is_pass,
            $model_version,
            $model_id = null,
            $model_label = null,
            $model_template_id = null,
            $chunkReviewPassword = null
    ) {

        $revisionNumber = ReviewUtils::sourcePageToRevisionNumber( $source_page );

        $feedback = null;
        if($chunkReviewPassword){
            $feedback = ( new FeedbackDAO() )->getFeedback( $jStruct->id, $chunkReviewPassword, $revisionNumber );
        }

        return [
            'revision_number'            => $revisionNumber,
            'feedback'                   => ( $feedback and isset( $feedback[ 'feedback' ] ) ) ? $feedback[ 'feedback' ] : null,
            'model_version'              => ( $model_version ? (int)$model_version : null ),
            'model_id'                   => ( !empty( $model_id ) ? (int)$model_id : null ),
            'model_label'                => ( !empty( $model_label ) ? $model_label : null ),
            'model_template_id'          => ( $model_template_id ? (int)$model_template_id : null ),
            'is_pass'                    => $is_pass,
            'quality_overall'            => $quality_overall,
            'errors_count'               => (int)$jStruct->getErrorsCount(),
            'revise_issues'              => $reviseIssues,
            'score'                      => floatval( $score ),
            'categories'                 => $categories,
            'total_issues_weight'        => (float)$total_issues_weight,
            'total_reviewed_words_count' => (int)$total_reviewed_words_count,
            'passfail'                   => $passfail,
            'total_time_to_edit'         => (int)$total_tte,
            'details'                    => self::getDetails($jStruct->id, $jStruct->password, $revisionNumber+1),
        ];
    }

    /**
     * @param Chunks_ChunkStruct     $jStruct
     * @param Projects_ProjectStruct $project
     * @param                        $chunkReview
     *
     * @return array
     * @throws Exception
     * @internal param $reviseIssues
     */
    protected static function revisionQualityVars( Chunks_ChunkStruct $jStruct, Projects_ProjectStruct $project, $chunkReview ) {

        $reviseIssues = [];

        $qualityReportDao = new QualityReportDao();
        $qa_data          = $qualityReportDao->getReviseIssuesByChunk( $jStruct->id, $jStruct->password, $chunkReview->source_page );
        foreach ( $qa_data as $issue ) {
            if ( !isset( $reviseIssues[ $issue->id_category ] ) ) {
                $reviseIssues[ $issue->id_category ] = [
                        'name'   => $issue->issue_category_label,
                        'founds' => [
                                $issue->issue_severity => 1
                        ]
                ];
            } else {
                if ( !isset( $reviseIssues[ $issue->id_category ][ 'founds' ][ $issue->issue_severity ] ) ) {
                    $reviseIssues[ $issue->id_category ][ 'founds' ][ $issue->issue_severity ] = 1;
                } else {
                    $reviseIssues[ $issue->id_category ][ 'founds' ][ $issue->issue_severity ]++;
                }
            }
        }

        if ( @$chunkReview->is_pass === null ) {
            $quality_overall = null;
            $is_pass         = null;
        } elseif ( !empty( $chunkReview->is_pass ) ) {
            $quality_overall = 'excellent';
            $is_pass         = (bool)$chunkReview->is_pass;
        } else {
            $quality_overall = 'fail';
            $is_pass         = false;
        }

        $chunkReviewModel = RevisionFactory::initFromProject( $project )->getChunkReviewModel( $chunkReview );

        $score = number_format( $chunkReviewModel->getScore(), 2, ".", "" );

        $total_issues_weight        = $chunkReviewModel->getPenaltyPoints();
        $total_reviewed_words_count = $chunkReviewModel->getReviewedWordsCount();

        $model      = $project->getLqaModel();
        $categories = $model !== null ? $model->getCategoriesAndSeverities() : [];

        if($model){
            $passFail = [ 'type' => $model->pass_type, 'options' => [ 'limit' => $chunkReviewModel->getQALimit( $model ) ] ];
        } else {
            $passFail = true;
        }

        return [
                $passFail,
                $reviseIssues,
                $quality_overall,
                $is_pass,
                $score,
                $total_issues_weight,
                $total_reviewed_words_count,
                $categories,
                ($model ? $model->hash : null),
                ($model ? $model->id : null),
                ($model ? $model->label : null),
                ($model ? $model->qa_model_template_id : null)
        ];
    }

    private static function getDetails($idJob, $password, $revisionNumber) {

        $details = [];

        $fileParts = \Jobs_JobDao::getReviewedWordsCountGroupedByFileParts($idJob, $password, $revisionNumber);

        foreach ($fileParts as $filePart){

            $originalFileName = $filePart->filename;
            if(null !== $filePart->id_file_part_external_reference and $filePart->tag_key === 'original'){
                $originalFileName = $filePart->tag_value;
            }

            $issuesGroupedByIdFilePart = (new EntryDao())->getIssuesGroupedByIdFilePart($idJob, $password, $revisionNumber, $filePart->id_file_part);

            $issues = [];
            $issuesWeight = 0;

            foreach ($issuesGroupedByIdFilePart as $issue){
                $issuesWeight = $issuesWeight +  $issue->penalty_points;
                $catCode = json_decode($issue->cat_options);
                $issues[] = [
                    'segment_id' => (int)$issue->segment_id,
                    'content_id' => $issue->content_id,
                    'penalty_points' => floatval($issue->penalty_points),
                    'category_code' => !empty($catCode) ? @$catCode->code : null,
                    'category_label' => $issue->cat_label,
                    'severity_code' =>  substr($issue->severity_label, 0, 3),
                    'severity_label' => $issue->severity_label,
                ];
            }

            $details[] = [
                    'id_file' => (int)$filePart->id_file,
                    'id_file_part' => ($filePart->id_file_part !== null) ? (int)$filePart->id_file_part : null,
                    'original_filename' => $originalFileName,
                    'reviewed_words_count' => floatval($filePart->reviewed_words_count),
                    'issues_weight' => floatval($issuesWeight),
                    'issues_entries' => count($issuesGroupedByIdFilePart),
                    'issues' => $issues,
            ];
        }

        return $details;

    }
}