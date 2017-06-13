<?php

namespace Features;

use TaskRunner\Commons\QueueElement;
use Translations\WarningModel;
use Translations\WarningStruct;

use Translations\WarningDao ;

use AMQHandler ;
use WorkerClient ;

class QaCheckGlossary extends BaseFeature {

    const GLOSSARY_SCOPE = 'glossary';

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
                '\Features\QaCheckGlossary\Worker\GlossaryWorker',
                $queue_element,
                array( 'persistent' => WorkerClient::$_HANDLER->persistent )
        );
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
               'propagated_ids'     => $params['propagated_ids'],
               'recheck_translation' => true
       ) ;

       self::enqueueTranslationCheck( $queue_element ) ;
    }


    public function filterGlobalWarnings($data, $params) {

        /** @var  $chunk \Chunks_ChunkStruct */
        $chunk = $params['chunk'];

        $warnings = WarningDao::findByChunkAndScope( $chunk, self::GLOSSARY_SCOPE );

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

        $data[self::GLOSSARY_SCOPE] = array('matches' => $data_elements ) ;
        return $data ;

    }
}