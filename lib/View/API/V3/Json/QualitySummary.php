<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 25/10/19
 * Time: 18:45
 *
 */

namespace View\API\V3\Json;


use Exception;
use Features\ReviewExtended\ReviewUtils;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewStruct;
use Model\LQA\EntryDao;
use Model\Projects\ProjectStruct;
use Model\QualityReport\QualityReportDao;
use Model\ReviseFeedback\FeedbackDAO;
use ReflectionException;
use RevisionFactory;

class QualitySummary {

    /**
     * @var JobStruct
     */
    protected JobStruct $chunk;
    /**
     * @var ProjectStruct
     */
    protected ProjectStruct $project;

    /**
     * QualitySummary constructor.
     *
     * @param JobStruct     $chunk
     * @param ProjectStruct $project
     */
    public function __construct( JobStruct $chunk, ProjectStruct $project ) {
        $this->chunk   = $chunk;
        $this->project = $project;
    }

    /**
     * @param ChunkReviewStruct[] $chunkReviewList
     *
     * @return array
     * @throws Exception
     */
    public function render( array $chunkReviewList ): array {

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
     * @return array
     * @throws Exception
     */
    protected function renderItem( ChunkReviewStruct $chunkReview ): array {

        [
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
        ] = self::revisionQualityVars( $this->chunk, $this->project, $chunkReview );

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
     * @param int            $source_page
     * @param JobStruct      $jStruct
     * @param                $quality_overall
     * @param array          $reviseIssues
     * @param float          $score
     * @param array          $categories
     * @param float|null     $total_issues_weight
     * @param int            $total_reviewed_words_count
     * @param array          $passfail
     *
     * @param float          $total_tte
     * @param bool|null      $is_pass
     *
     * @param int            $model_version
     * @param int|null       $model_id
     * @param string|null    $model_label
     * @param int|null       $model_template_id
     *
     * @param string|null    $chunkReviewPassword
     *
     * @return array
     * @throws ReflectionException
     */
    private static function populateQualitySummarySection(
            int $source_page,
            JobStruct $jStruct,
            $quality_overall,
            array $reviseIssues,
            float $score,
            array $categories,
            ?float $total_issues_weight,
            int $total_reviewed_words_count,
            array $passfail,
            float $total_tte,
            ?bool $is_pass,
            int $model_version,
            int $model_id = null,
            ?string $model_label = null,
            ?int $model_template_id = null,
            ?string $chunkReviewPassword = null
    ): array {

        $revisionNumber = ReviewUtils::sourcePageToRevisionNumber( $source_page );

        $feedback = null;
        if ( $chunkReviewPassword ) {
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
                'errors_count'               => $jStruct->getErrorsCount(),
                'revise_issues'              => $reviseIssues,
                'score'                      => floatval( $score ),
                'categories'                 => $categories,
                'total_issues_weight'        => (float)$total_issues_weight,
                'total_reviewed_words_count' => (int)$total_reviewed_words_count,
                'passfail'                   => $passfail,
                'total_time_to_edit'         => (int)$total_tte,
                'details'                    => self::getDetails( $jStruct->id, $jStruct->password, $revisionNumber + 1 ),
        ];
    }

    /**
     * @param JobStruct         $jStruct
     * @param ProjectStruct     $project
     * @param ChunkReviewStruct $chunkReview
     *
     * @return array
     * @throws Exception
     * @internal param $reviseIssues
     */
    protected static function revisionQualityVars( JobStruct $jStruct, ProjectStruct $project, ChunkReviewStruct $chunkReview ): array {

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

        if ( $model ) {
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
                ( $model ? $model->hash : null ),
                ( $model ? $model->id : null ),
                ( $model ? $model->label : null ),
                ( $model ? $model->qa_model_template_id : null )
        ];
    }

    /**
     * @throws ReflectionException
     */
    private static function getDetails( int $idJob, string $password, int $revisionNumber ): array {

        $details = [];

        $fileParts = JobDao::getReviewedWordsCountGroupedByFileParts( $idJob, $password, $revisionNumber );

        foreach ( $fileParts as $filePart ) {

            $originalFileName = $filePart->filename;
            if ( null !== $filePart->id_file_part_external_reference and $filePart->tag_key === 'original' ) {
                $originalFileName = $filePart->tag_value;
            }

            $issuesGroupedByIdFilePart = ( new EntryDao() )->getIssuesGroupedByIdFilePart( $idJob, $password, $revisionNumber, $filePart->id_file_part );

            $issues       = [];
            $issuesWeight = 0;

            foreach ( $issuesGroupedByIdFilePart as $issue ) {
                $issuesWeight = $issuesWeight + $issue->penalty_points;
                $catCode      = json_decode( $issue->cat_options );
                $issues[]     = [
                        'segment_id'     => (int)$issue->segment_id,
                        'content_id'     => $issue->content_id,
                        'penalty_points' => floatval( $issue->penalty_points ),
                        'category_code'  => !empty( $catCode ) ? @$catCode->code : null,
                        'category_label' => $issue->cat_label,
                        'severity_code'  => substr( $issue->severity_label, 0, 3 ),
                        'severity_label' => $issue->severity_label,
                ];
            }

            $details[] = [
                    'id_file'              => (int)$filePart->id_file,
                    'id_file_part'         => ( $filePart->id_file_part !== null ) ? (int)$filePart->id_file_part : null,
                    'original_filename'    => $originalFileName,
                    'reviewed_words_count' => floatval( $filePart->reviewed_words_count ),
                    'issues_weight'        => floatval( $issuesWeight ),
                    'issues_entries'       => count( $issuesGroupedByIdFilePart ),
                    'issues'               => $issues,
            ];
        }

        return $details;

    }
}