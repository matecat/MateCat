<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 13/09/2018
 * Time: 16:16
 */

namespace API\App\Json;

use API\V2\Json\JobTranslator;
use API\V2\Json\ProjectUrls;
use API\V3\Json\Chunk;
use API\V3\Json\QualitySummary;
use CatUtils;
use Chunks_ChunkStruct;
use DataAccess\ShapelessConcreteStruct;
use Features\QaCheckBlacklist\Utils\BlacklistUtils;
use Features\ReviewExtended\ReviewUtils;
use FeatureSet;
use Glossary\Blacklist\BlacklistDao;
use Langs_LanguageDomains;
use Langs_Languages;
use Projects_MetadataDao;
use Projects_ProjectDao;
use Projects_ProjectStruct;
use RedisHandler;
use Utils;
use WordCount\WordCountStruct;

/**
 * ( 2023/11/06 )
 *
 * This class is meant to allow back compatibility with running projects
 * after the advancement word-count switch from weighted to raw
 *
 * YYY [Remove] backward compatibility for current projects
 * YYY Remove after a reasonable amount of time
 */
class CompatibilityChunk extends Chunk {


    /**
     * @param                         $chunk Chunks_ChunkStruct
     *
     * @param Projects_ProjectStruct  $project
     * @param FeatureSet              $featureSet
     *
     * @return array
     * @throws \Exception
     */
    public function renderItem( Chunks_ChunkStruct $chunk, Projects_ProjectStruct $project, FeatureSet $featureSet ) {

        $this->chunk   = $chunk;
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

        $warningsCount = $chunk->getWarningsCount();

        // blacklistWordsCount
        $blacklistWordsCount = null;

        $dao = new BlacklistDao();
        $dao->destroyGetByJobIdAndPasswordCache( $chunk->id, $chunk->password );
        $model = $dao->getByJobIdAndPassword( $chunk->id, $chunk->password );

        if ( !empty( $model ) ) {
            $blacklistUtils      = new BlacklistUtils( ( new RedisHandler() )->getConnection() );
            $abstractBlacklist   = $blacklistUtils->getAbstractBlacklist( $chunk );
            $blacklistWordsCount = $abstractBlacklist->getWordsCount();
        }

        $result = [
                'id'                      => (int)$chunk->id,
                'password'                => $chunk->password,
                'source'                  => $chunk->source,
                'target'                  => $chunk->target,
                'sourceTxt'               => $lang_handler->getLocalizedName( $chunk->source ),
                'targetTxt'               => $lang_handler->getLocalizedName( $chunk->target ),
                'status'                  => $chunk->status_owner,
                'subject'                 => $chunk->subject,
                'subject_printable'       => $subjectsHashMap[ $chunk->subject ],
                'owner'                   => $chunk->owner,
                'time_to_edit'            => $this->getTimeToEditArray( $chunk->id ),
                'total_time_to_edit'      => (int)$chunk->total_time_to_edit,
                'avg_post_editing_effort' => (float)$chunk->avg_post_editing_effort,
                'open_threads_count'      => (int)$chunk->getOpenThreadsCount(),
                'created_at'              => Utils::api_timestamp( $chunk->create_date ),
                'pee'                     => $chunk->getPeeForTranslatedSegments(),
                'private_tm_key'          => $this->getKeyList( $chunk ),
                'warnings_count'          => $warningsCount->warnings_count,
                'warning_segments'        => ( isset( $warningsCount->warning_segments ) ? $warningsCount->warning_segments : [] ),
                'stats'                   => ( $chunk->getProject()->getWordCountType() == Projects_MetadataDao::WORD_COUNT_RAW ? $jobStats : $this->_getStats( $jobStats ) ),
                'outsource'               => $outsource,
                'translator'              => $translator,
                'total_raw_wc'            => (int)$chunk->total_raw_wc,
                'standard_wc'             => (float)$chunk->standard_analysis_wc,
                'blacklist_word_count'    => $blacklistWordsCount,
        ];


        $chunkReviewsList = $this->getChunkReviews();

        $result = array_merge( $result, ( new QualitySummary( $chunk, $project ) )->render( $chunkReviewsList ) );

        foreach ( $chunkReviewsList as $index => $chunkReview ) {
            $result = static::populateRevisePasswords( $chunkReview, $result );
        }


        /**
         * @var $projectData ShapelessConcreteStruct[]
         */
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

    protected function _getStats( $jobStats ) {
        $stats = CatUtils::getPlainStatsForJobs( $jobStats );
        unset( $stats [ 'id' ] );
        $stats = array_change_key_case( $stats, CASE_LOWER );

        return ReviewUtils::formatStats( $stats, $this->getChunkReviews() );
    }

}