<?php

namespace Features\QaCheckGlossary\Worker ;

use Features\QaCheckGlossary;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Commons\QueueElement;
use Translations\WarningModel;
use Translations\WarningStruct;

class GlossaryWorker extends AbstractWorker {

    /** @var $queueElement QueueElement */
    protected $queueElement ;

    protected $unmatched ;

    public function process( AbstractElement $queueElement ) {

        $this->_checkDatabaseConnection();

        $this->queueElement  = $queueElement ;

        $this->_checkForReQueueEnd( $this->queueElement );

        if ( $queueElement->params->recheck_translation && $this->_translationChanged()  ) {
            return true;
        }

        $this->_updateWarnings();
    }


    protected function _translationChanged() {
        $translation = \Translations_SegmentTranslationDao::findBySegmentAndJob(
            $this->queueElement->params['id_segment'],
            $this->queueElement->params['id_job']
        );

        return $translation->translation != $this->queueElement->params['translation'] ;
    }

    protected function _updateWarnings( ) {
        $params = $this->queueElement->params ;

        $job = \Jobs_JobDao::getById( $params['id_job'] )[0];

        $glossaryModel = new \GlossaryModel( $job );

        $this->unmatched = $glossaryModel->getUnmatched( $params['segment'], $params['translation'] );

        $this->_updateWarningsOnSegmentId( $params['id_job'], $params['id_segment'] ) ;

        if ( !empty( $params['propagated_ids']) ) {
            $this->_propagateWarnings( ) ;
        }
    }

    protected function _propagateWarnings( ) {
        foreach( $this->queueElement->params['propagated_ids'] as $id_segment ) {
            $this->_updateWarningsOnSegmentId($this->queueElement->params['id_job'], $id_segment ) ;
        }
    }

    protected function _updateWarningsOnSegmentId( $id_job, $id_segment ) {
        $warningModel = new WarningModel(  $id_job, $id_segment );
        $warningModel->start();
        $warningModel->resetScope( QaCheckGlossary::GLOSSARY_SCOPE );

        foreach ( $this->unmatched as $entry ) {
            $warning = new WarningStruct( array(
                    'id_job'     => $id_job,
                    'id_segment' => $id_segment,
                    'scope'      => QaCheckGlossary::GLOSSARY_SCOPE,
                    'severity'   => WarningModel::WARNING,
                    'data'       => json_encode( $this->_formatWarningData( $entry ) )
            ) );

            $warningModel->addWarning( $warning );
        }
        $warningModel->save();
    }

    /**
     * This function controls what we write in  data field, to avoid to pass the raw TM response to clients.
     *
     * @param $warning
     *
     * @return array
     */
    protected function _formatWarningData( $warning ) {
        return array(
                'raw_segment'        => $warning['raw_segment'],
                'raw_translation'    => $warning['raw_translation']
        );
    }

}