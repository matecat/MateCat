<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 25/05/16
 * Time: 17:23
 */

namespace Features;

use Translations\WarningDao ;
use Translations\WarningStruct ;
use Features\QaCheckBlacklist\BlacklistFromZip ;
use ProjectModel ;

use Translations\WarningModel ;

use TaskRunner\Commons\QueueElement ;
use WorkerClient ;
use AMQHandler ;

class QaCheckBlacklist extends BaseFeature {

    const BLACKLIST_SCOPE = 'blacklist' ;


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

    protected static function enqueueTranslationCheck( $queue_element ) {
        WorkerClient::init( new AMQHandler() );
        WorkerClient::enqueue( 'QA_CHECKS',
                '\Features\QaCheckBlacklist\Worker\BlacklistWorker',
                $queue_element,
                array( 'persistent' => WorkerClient::$_HANDLER->persistent )
        );
    }

    public function postProjectCreate( $projectStructure ) {
        $project_struct = \Projects_ProjectDao::findById( $projectStructure[ 'result' ][ 'id_project' ] );
        $project_model = new ProjectModel($project_struct );
        $project_model->saveBlacklistPresence();
    }

    public function setTranslationCommitted( $params ) {
        $translation = $params[ 'translation' ];

        /** @var  $segment \Segments_SegmentStruct */
        $segment = $params[ 'segment' ];

        /** @var $chunk \Chunks_ChunkStruct */
        $chunk = $params[ 'chunk' ];

        $queue_element = array(
                'id_segment'          => $translation['id_segment'],
                'id_job'              => $translation['id_job'],
                'id_project'          => $chunk->id_project,
                'segment'             => $segment['segment'],
                'translation'         => $translation['translation'],
                'recheck_translation' => true,
                'propagated_ids'      => $params['propagated_ids']
        ) ;

        self::enqueueTranslationCheck( $queue_element ) ;

    }

    public function filterGlobalWarnings($data, $params) {
        /**
         * @var $chunk \Chunks_ChunkStruct
         */
        $chunk =$params['chunk'] ;

        $warnings = WarningDao::findByChunkAndScope( $chunk, self::BLACKLIST_SCOPE );

        $data_elements = array() ;

        if ( count($warnings) > 0 ) {
            $data_elements = array_map(function(WarningStruct $element) {
                return array(
                        'id_segment' => $element->id_segment,
                        'severity' => $element->severity,
                        'data' => json_decode( $element->data, TRUE )
                );
            }, $warnings);
        }

        $data[self::BLACKLIST_SCOPE] = array('matches' => $data_elements ) ;
        return $data ;

    }

    public function filterSegmentWarnings( $data, $params ) {
        $params = \Utils::ensure_keys( $params, array(
                'src_content', 'trg_content', 'chunk', 'project'
        ));

        $target = $params['trg_content'] ;

        /**
         * @var $project \Projects_ProjectStruct
         */
        $project = $params['project'];

        /**
         * @var $chunk \Chunks_ChunkStruct
         */
        $chunk =$params['chunk'] ;

        $blacklist = new BlacklistFromZip( $project->getFirstOriginalZipPath(),  $chunk->id ) ;

        $data['blacklist'] = array(
                'matches' => $blacklist->getMatches( $target )
        );

        return $data;
    }
    
}