<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 25/05/16
 * Time: 17:23
 */

namespace Features;

use AMQHandler;
use Chunks_ChunkStruct;
use Exception;
use Features\QaCheckBlacklist\Utils\BlacklistUtils;
use Projects\ProjectModel;
use Projects_ProjectDao;
use RedisHandler;
use ReflectionException;
use Segments_SegmentStruct;
use TaskRunner\Commons\QueueElement;
use Translations\WarningDao;
use Translations\WarningStruct;
use Utils;
use WorkerClient;

class QaCheckBlacklist extends BaseFeature {

    const FEATURE_CODE = 'qa_check_blacklist';

    const BLACKLIST_SCOPE = 'blacklist' ;

    /**
     * @throws Exception
     */
    public function postTMSegmentAnalyzed( $params ) {
        $tm_data = $params['tm_data'];

        /**
         * @var $analysis_queue_element QueueElement
         */
        $analysis_queue_element = $params['queue_element'] ;

        $queue_element = array(
                'id_segment'          => $analysis_queue_element->params->id_segment,
                'id_job'              => $analysis_queue_element->params->id_job,
                'id_project'          => $analysis_queue_element->params->pid,
                'segment'             => $analysis_queue_element->params->segment,
                'translation'         => $tm_data[ 'translation' ],
                'recheck_translation' => false
        ) ;

        self::enqueueTranslationCheck( $queue_element );

    }

    /**
     * @throws Exception
     */
    protected static function enqueueTranslationCheck( $queue_element ) {
        WorkerClient::enqueue( 'QA_CHECKS',
                '\Features\QaCheckBlacklist\Worker\BlacklistWorker',
                $queue_element,
                array( 'persistent' => WorkerClient::$_HANDLER->persistent )
        );
    }

    /**
     * @throws Exception
     */
    public function postProjectCreate( $projectStructure ) {
        $project_struct = Projects_ProjectDao::findById( $projectStructure[ 'result' ][ 'id_project' ] );
        $project_model = new ProjectModel($project_struct );
        $project_model->saveBlacklistPresence();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function setTranslationCommitted( $params ) {
        $translation = $params[ 'translation' ];

        /** @var  $segment Segments_SegmentStruct */
        $segment = $params[ 'segment' ];

        /** @var $chunk Chunks_ChunkStruct */
        $chunk = $params[ 'chunk' ];

        $blacklistUtils = new BlacklistUtils( ( new RedisHandler() )->getConnection() );

        $queue_element = [
            'id_segment'          => $translation['id_segment'],
            'id_job'              => $translation['id_job'],
            'job_password'        => $chunk->password,
            'id_project'          => $chunk->id_project,
            'segment'             => $segment['segment'],
            'translation'         => $translation['translation'],
            'recheck_translation' => true,
            'from_upload'         => $blacklistUtils->checkIfExists($chunk->id, $chunk->password),
            'propagated_ids'      => $params['propagated_ids']
        ] ;

        self::enqueueTranslationCheck( $queue_element ) ;
    }



    public function filterGlobalWarnings( $result, $params ) {
        /**
         * @var $chunk Chunks_ChunkStruct
         */
        $chunk = $params[ 'chunk' ];

        $warnings = WarningDao::findByChunkAndScope( $chunk, self::BLACKLIST_SCOPE );

        $data_elements = [];

        if ( count( $warnings ) > 0 ) {
            $data_elements = array_map( function ( WarningStruct $element ) {
                return [
                        'id_segment' => $element->id_segment,
                        'severity'   => $element->severity,
                        'data'       => json_decode( $element->data, true )
                ];
            }, $warnings );
        }

        $result[ 'data' ][ self::BLACKLIST_SCOPE ] = [ 'matches' => $data_elements ];

        return $result;

    }

    public function filterSegmentWarnings( $data, $params ) {
        $params = Utils::ensure_keys( $params, array(
                'src_content', 'trg_content', 'chunk', 'project'
        ));

        $target = $params['trg_content'] ;

        /**
         * @var $chunk Chunks_ChunkStruct
         */
        $chunk = $params['chunk'] ;

        $blacklistUtils = new BlacklistUtils( ( new RedisHandler() )->getConnection() );
        $blacklist = $blacklistUtils->getAbstractBlacklist($chunk);

        $data['blacklist'] = [
                'matches' => $blacklist->getMatches( $target )
        ];

        return $data;
    }
    
}