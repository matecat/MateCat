<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/04/17
 * Time: 21.42
 *
 */

namespace API\V2\Json;


use API\App\Json\OutsourceConfirmation;
use CatUtils;
use Chunks_ChunkStruct;
use Exception;
use Features\ReviewExtended\ReviewUtils as ReviewUtils;
use FeatureSet;
use Langs_LanguageDomains;
use Langs_Languages;
use LQA\ChunkReviewDao;
use ManageUtils;
use OutsourceTo_OutsourceAvailable;
use Projects_ProjectDao;
use Projects_ProjectStruct;
use TmKeyManagement_ClientTmKeyStruct;
use TmKeyManagement_Filter;
use Users_UserStruct;
use Utils;
use WordCount\WordCountStruct;

class Job {

    /**
     * @var string
     */
    protected $status;

    /**
     * @var Users_UserStruct
     */
    protected $user;

    /**
     * @var bool
     */
    protected $called_from_api = false;

    /**
     * @var TmKeyManagement_ClientTmKeyStruct[]
     */
    protected $keyList = [];

    /**
     * @param mixed $status
     */
    public function setStatus( $status ) {
        $this->status = $status;
    }

    /**
     * @param Users_UserStruct $user
     *
     * @return $this
     */
    public function setUser( Users_UserStruct $user = null ) {
        $this->user = $user;

        return $this;
    }

    /**
     * @param bool $called_from_api
     *
     * @return $this
     */
    public function setCalledFromApi( $called_from_api ) {
        $this->called_from_api = (bool)$called_from_api;

        return $this;
    }

    /**
     * @param Chunks_ChunkStruct $jStruct
     *
     * @return array
     */
    protected function getKeyList( Chunks_ChunkStruct $jStruct ) {

        if ( empty( $this->user ) ) {
            return [];
        }

        if ( !$this->called_from_api ) {
            $out = $jStruct->getClientKeys( $this->user, TmKeyManagement_Filter::OWNER )[ 'job_keys' ];
        } else {
            $out = $jStruct->getClientKeys( $this->user, TmKeyManagement_Filter::ROLE_TRANSLATOR )[ 'job_keys' ];
        }

        return ( new JobClientKeys( $out ) )->render();

    }

    /**
     * @param                         $chunk Chunks_ChunkStruct
     *
     * @param Projects_ProjectStruct  $project
     * @param FeatureSet              $featureSet
     *
     * @return array
     * @throws Exception
     */
    public function renderItem( Chunks_ChunkStruct $chunk, Projects_ProjectStruct $project, FeatureSet $featureSet ) {

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

        $lang_handler = Langs_Languages::getInstance();

        $subject_handler = Langs_LanguageDomains::getInstance();
        $subjectsHashMap = $subject_handler->getEnabledHashMap();

        $warningsCount   = $chunk->getWarningsCount();

        // Added 5 minutes cache here
        $chunkReviews = ( new ChunkReviewDao() )->findChunkReviews( $chunk, 60 * 5 );

        // is outsource available?
        $outsourceAvailableInfo = $featureSet->filter( 'outsourceAvailableInfo', $chunk->target, $chunk->getProject()->id_customer, $chunk->id );

        // if the hook is not triggered by any plugin
        if ( !is_array( $outsourceAvailableInfo ) or empty( $outsourceAvailableInfo ) ) {
            $outsourceAvailableInfo = [
                    'disabled_email'         => false,
                    'custom_payable_rate'    => false,
                    'language_not_supported' => false,
            ];
        }

        $outsourceAvailable = OutsourceTo_OutsourceAvailable::isOutsourceAvailable( $outsourceAvailableInfo );

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
                'formatted_create_date' => ManageUtils::formatJobDate( $chunk->create_date ),
                'quality_overall'       => CatUtils::getQualityOverallFromJobStruct( $chunk, $chunkReviews ),
                'pee'                   => $chunk->getPeeForTranslatedSegments(),
                'tte'                   => (int)( (int)$chunk->total_time_to_edit / 1000 ),
                'private_tm_key'        => $this->getKeyList( $chunk ),
                'warnings_count'        => $warningsCount->warnings_count,
                'warning_segments'      => ( isset( $warningsCount->warning_segments ) ? $warningsCount->warning_segments : [] ),
                'word_count_type'       => $chunk->getProject()->getWordCountType(),
                'stats'                 => $jobStats,
                'outsource'             => $outsource,
                'outsource_available'   => $outsourceAvailable,
                'outsource_info'        => $outsourceAvailableInfo,
                'translator'            => $translator,
                'total_raw_wc'          => (int)$chunk->total_raw_wc,
                'standard_wc'           => (float)$chunk->standard_analysis_wc,
                'quality_summary'       => [
                        'quality_overall' => $chunk->getQualityOverall( $chunkReviews ),
                        'errors_count'    => (int)$chunk->getErrorsCount()
                ],

        ];

        // add revise_passwords to stats
        foreach ( $chunkReviews as $chunk_review ) {

            if ( $chunk_review->source_page <= \Constants::SOURCE_PAGE_REVISION ) {
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

        $project = $chunk->getProject();

        $projectData = ( new Projects_ProjectDao() )->setCacheTTL( 60 * 60 * 24 )->getProjectData( $project->id, $project->password );

        $formatted = new ProjectUrls( $projectData );

        /** @var $formatted ProjectUrls */
        $formatted = $featureSet->filter( 'projectUrls', $formatted );

        $urlsObject       = $formatted->render( true );
        $result[ 'urls' ] = $urlsObject[ 'jobs' ][ $chunk->id ][ 'chunks' ][ $chunk->password ];

        $result[ 'urls' ][ 'original_download_url' ]    = $urlsObject[ 'jobs' ][ $chunk->id ][ 'original_download_url' ];
        $result[ 'urls' ][ 'translation_download_url' ] = $urlsObject[ 'jobs' ][ $chunk->id ][ 'translation_download_url' ];
        $result[ 'urls' ][ 'xliff_download_url' ]       = $urlsObject[ 'jobs' ][ $chunk->id ][ 'xliff_download_url' ];

        return $result;

    }

}