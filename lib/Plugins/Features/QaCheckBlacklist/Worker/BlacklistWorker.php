<?php

namespace Features\QaCheckBlacklist\Worker;

use Features\QaCheckBlacklist;
use Features\QaCheckBlacklist\AbstractBlacklist;
use Features\QaCheckBlacklist\Utils\BlacklistUtils;
use Jobs_JobDao;
use Projects_MetadataDao;
use RedisHandler;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Commons\QueueElement;
use Translations\WarningModel;
use Translations\WarningStruct;
use Translations_SegmentTranslationDao;

class BlacklistWorker extends AbstractWorker {

    /** @var $queueElement QueueElement */
    protected $queueElement;

    protected $matches;

    public function process( AbstractElement $queueElement ) {

        $this->_checkDatabaseConnection();

        $this->queueElement = $queueElement;

        $this->_checkForReQueueEnd( $this->queueElement );

        if ( $queueElement->params->recheck_translation && $this->_translationChanged() ) {
            return true;
        }

        $this->_updateWarnings();
    }


    protected function _translationChanged() {
        $translation = Translations_SegmentTranslationDao::findBySegmentAndJob(
                $this->queueElement->params[ 'id_segment' ],
                $this->queueElement->params[ 'id_job' ]
        );

        return $translation->translation != $this->queueElement->params[ 'translation' ];
    }

    protected function _updateWarnings() {
        $params = $this->queueElement->params;

        $dao           = new Projects_MetadataDao();
        $has_blacklist = $dao->setCacheTTL( 60 * 60 * 24 )->get( $params[ 'id_project' ], 'has_blacklist' );

        if ( !$has_blacklist ) {
            return;
        }

        $blacklist = $this->getAbstractBlacklist( $params );

        $this->matches = $blacklist->getMatches( $params[ 'translation' ] );

        $this->_updateWarningsOnSegmentId( $params[ 'id_job' ], $params[ 'id_segment' ] );

        if ( !empty( $this->queueElement->params[ 'propagated_ids' ] ) ) {
            $this->_propagateWarnings();
        }
    }

    /**
     * @param $params
     *
     * @return AbstractBlacklist
     * @throws \Exception
     */
    private function getAbstractBlacklist( $params ) {
        $job            = ( isset( $params[ 'from_upload' ] ) and isset( $params[ 'job_password' ] ) ) ? Jobs_JobDao::getByIdAndPassword( $params[ 'id_job' ], $params[ 'job_password' ] ) : Jobs_JobDao::getById( $params[ 'id_job' ] )[ 0 ];
        $blacklistUtils = new BlacklistUtils( ( new RedisHandler() )->getConnection() );

        return $blacklistUtils->getAbstractBlacklist( $job );
    }

    protected function _propagateWarnings() {
        foreach ( $this->queueElement->params[ 'propagated_ids' ] as $id_segment ) {
            $this->_updateWarningsOnSegmentId( $this->queueElement->params[ 'id_job' ], $id_segment );
        }
    }

    protected function _updateWarningsOnSegmentId( $id_job, $id_segment ) {
        $warningModel = new WarningModel(
                $id_job, $id_segment
        );
        $warningModel->start();
        $warningModel->resetScope( QaCheckBlacklist::BLACKLIST_SCOPE );

        foreach ( $this->matches as $match => $matchData ) {
            for ( $k = 0; $k < $matchData[ 'count' ]; $k++ ) {
                $warning = new WarningStruct( [
                        'id_job'     => $id_job,
                        'id_segment' => $id_segment,
                        'severity'   => WarningModel::WARNING,
                        'scope'      => QaCheckBlacklist::BLACKLIST_SCOPE,
                        'data'       => '{"match":"' . $matchData[ 'match' ] . '", "count":"' . $matchData[ 'count' ] . '", "positions": ' . json_encode( $matchData[ 'positions' ] ) . '}'
                ] );
                $warningModel->addWarning( $warning );
            }
        }

        $warningModel->save();
    }

}