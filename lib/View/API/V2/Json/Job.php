<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/04/17
 * Time: 21.42
 *
 */

namespace View\API\V2\Json;


use Controller\API\Commons\Exceptions\AuthenticationError;
use Exception;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\Projects\ManageModel;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use Model\WordCount\WordCountStruct;
use Plugins\Features\ReviewExtended\ReviewUtils as ReviewUtils;
use ReflectionException;
use Utils\Constants\SourcePages;
use Utils\Langs\LanguageDomains;
use Utils\Langs\Languages;
use Utils\OutsourceTo\OutsourceAvailable;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;
use Utils\TmKeyManagement\Filter;
use Utils\Tools\CatUtils;
use Utils\Tools\Utils;
use View\API\App\Json\OutsourceConfirmation;

class Job {

    /**
     * @var ?string
     */
    protected ?string $status = null;

    /**
     * @var UserStruct
     */
    protected UserStruct $user;

    /**
     * @var bool
     */
    protected bool $called_from_api = false;

    /**
     * @param string $status
     */
    public function setStatus( string $status ) {
        $this->status = $status;
    }

    /**
     * @param UserStruct $user
     *
     * @return $this
     */
    public function setUser( UserStruct $user ): Job {
        $this->user = $user;

        return $this;
    }

    /**
     * @param bool $called_from_api
     *
     * @return $this
     */
    public function setCalledFromApi( bool $called_from_api ): Job {
        $this->called_from_api = $called_from_api;

        return $this;
    }

    /**
     * @param JobStruct $jStruct
     *
     * @return array
     */
    protected function getKeyList( JobStruct $jStruct ): array {

        if ( empty( $this->user ) ) {
            return [];
        }

        if ( !$this->called_from_api ) {
            $out = $jStruct->getClientKeys( $this->user, Filter::OWNER )[ 'job_keys' ];
        } else {
            $out = $jStruct->getClientKeys( $this->user, Filter::ROLE_TRANSLATOR )[ 'job_keys' ];
        }

        return ( new JobClientKeys( $out ) )->render();

    }

    /**
     * @param                         $chunk JobStruct
     *
     * @param ProjectStruct           $project
     * @param FeatureSet              $featureSet
     *
     * @return array
     * @throws Exception
     */
    public function renderItem( JobStruct $chunk, ProjectStruct $project, FeatureSet $featureSet ): array {

        $outsourceInfo = $chunk->getOutsource();
        $tStruct       = $chunk->getTranslator();
        $outsource     = null;
        $translator    = null;
        if ( !empty( $outsourceInfo ) ) {
            $outsource = ( new OutsourceConfirmation( $outsourceInfo ) )->render();
        } else {
            $translator = ( !empty( $tStruct ) ? ( new JobTranslator( $tStruct ) )->renderItem() : null );
        }

        $jobStats = WordCountStruct::loadFromJob( $chunk );

        $lang_handler = Languages::getInstance();

        $subject_handler = LanguageDomains::getInstance();
        $subjectsHashMap = $subject_handler->getEnabledHashMap();

        $warningsCount = $chunk->getWarningsCount();

        // Added 5 minutes cache here
        $chunkReviews = ( new ChunkReviewDao() )->findChunkReviews( $chunk, 60 * 5 );

        // is outsource available?
        $outsourceAvailableInfo = $featureSet->filter( 'outsourceAvailableInfo', $chunk->target, $chunk->getProject()->id_customer, $chunk->id );

        // if any plugin doesn't trigger the hook
        if ( !is_array( $outsourceAvailableInfo ) or empty( $outsourceAvailableInfo ) ) {
            $outsourceAvailableInfo = [
                    'disabled_email'         => false,
                    'custom_payable_rate'    => false,
                    'language_not_supported' => false,
            ];
        }

        $outsourceAvailable = OutsourceAvailable::isOutsourceAvailable( $outsourceAvailableInfo );

        $result = [
                'id'                    => (int)$chunk->id,
                'password'              => $chunk->password,
                'source'                => $chunk->source,
                'target'                => $chunk->target,
                'sourceTxt'             => $lang_handler->getLocalizedName( $chunk->source ),
                'targetTxt'             => $lang_handler->getLocalizedName( $chunk->target ),
                'job_first_segment'     => $chunk->job_first_segment,
                'status'                => $chunk->status_owner,
                'subject'               => $chunk->subject,
                'subject_printable'     => $subjectsHashMap[ $chunk->subject ],
                'owner'                 => $chunk->owner,
                'open_threads_count'    => (int)$chunk->getOpenThreadsCount(),
                'create_timestamp'      => strtotime( $chunk->create_date ),
                'created_at'            => Utils::api_timestamp( $chunk->create_date ),
                'create_date'           => $chunk->create_date,
                'formatted_create_date' => ManageModel::formatJobDate( $chunk->create_date ),
                'quality_overall'       => CatUtils::getQualityOverallFromJobStruct( $chunk, $chunkReviews ),
                'pee'                   => $chunk->getPeeForTranslatedSegments(),
                'tte'                   => (int)( $chunk->total_time_to_edit / 1000 ),
                'private_tm_key'        => $this->getKeyList( $chunk ),
                'warnings_count'        => $warningsCount->warnings_count,
                'warning_segments'      => ( $warningsCount->warning_segments ?? [] ),
                'word_count_type'       => $chunk->getProject()->getWordCountType(),
                'stats'                 => $jobStats,
                'outsource'             => $outsource,
                'outsource_available'   => $outsourceAvailable,
                'outsource_info'        => $outsourceAvailableInfo,
                'translator'            => $translator,
                'total_raw_wc'          => $chunk->total_raw_wc,
                'standard_wc'           => (float)$chunk->standard_analysis_wc,
                'quality_summary'       => [
                        'quality_overall' => $chunk->getQualityOverall( $chunkReviews ),
                        'errors_count'    => $chunk->getErrorsCount()
                ],

        ];

        // add revise_passwords to stats
        foreach ( $chunkReviews as $chunk_review ) {

            if ( $chunk_review->source_page <= SourcePages::SOURCE_PAGE_REVISION ) {
                $result[ 'revise_passwords' ][] = [
                        'revision_number' => 1,
                        'password'        => $chunk_review->review_password
                ];
            } else {
                $result[ 'revise_passwords' ][] = [
                        'revision_number' => ReviewUtils::sourcePageToRevisionNumber( $chunk_review->source_page ),
                        'password'        => $chunk_review->review_password
                ];
            }

        }

        return $this->fillUrls( $result, $chunk, $project, $featureSet );

    }


    /**
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws ValidationError
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws Exception
     */
    protected function fillUrls( array $result, JobStruct $chunk, ProjectStruct $project, FeatureSet $featureSet ): array {

        $projectData = ( new ProjectDao() )->setCacheTTL( 60 * 60 * 24 )->getProjectData( $project->id, $project->password );

        $formatted = new ProjectUrls( $projectData );

        /** @var $formatted ProjectUrls */
        $formatted = $featureSet->filter( 'projectUrls', $formatted );

        $urlsObject       = $formatted->render( true );
        $result[ 'urls' ] = $urlsObject[ 'jobs' ][ $chunk->id ][ 'chunks' ][ $chunk->password ] ?? "";

        $result[ 'urls' ][ 'original_download_url' ]    = $urlsObject[ 'jobs' ][ $chunk->id ][ 'original_download_url' ] ?? "";
        $result[ 'urls' ][ 'translation_download_url' ] = $urlsObject[ 'jobs' ][ $chunk->id ][ 'translation_download_url' ] ?? "";
        $result[ 'urls' ][ 'xliff_download_url' ]       = $urlsObject[ 'jobs' ][ $chunk->id ][ 'xliff_download_url' ] ?? "";

        return $result;

    }

}