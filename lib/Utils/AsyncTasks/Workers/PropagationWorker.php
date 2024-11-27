<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 21/11/24
 * Time: 17:56
 *
 */

namespace AsyncTasks\Workers;

use Database;
use Exception;
use Features\TranslationVersions\Model\TranslationVersionDao;
use Jobs_JobStruct;
use PDOException;
use Projects_ProjectStruct;
use Propagation_PropagationTotalStruct;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Commons\Params;
use TaskRunner\Commons\QueueElement;
use TaskRunner\Exceptions\EndQueueException;
use Translations_SegmentTranslationStruct;

class PropagationWorker extends AbstractWorker {

    /**
     * @inheritDoc
     * @throws EndQueueException
     * @throws Exception
     */
    public function process( AbstractElement $queueElement ) {
        /**
         * @var $queueElement QueueElement
         */
        $this->_checkForReQueueEnd( $queueElement );
        $this->_checkDatabaseConnection();

        /**
         * Cast to the proper objects from payload
         */
        $this->propagateTranslation( $this->rebuildObjects( $queueElement->params ) );

    }

    /**
     * @throws Exception
     */
    protected function propagateTranslation( array $structures ) {

        $translationVersionsDao = new TranslationVersionDao();

        /**
         * @var $propagationTotalStruct ?Propagation_PropagationTotalStruct
         */
        $propagationTotalStruct = $structures[ 'propagationAnalysis' ];

        /**
         * @var $propagatorSegment Translations_SegmentTranslationStruct
         */
        $propagatorSegment = $structures[ 'translationStructTemplate' ];

        if ( true === $structures[ 'execute_update' ] and !empty( $propagationTotalStruct->getSegmentsForPropagation() ) ) {

            try {

                $static_field_values = [
                        'id_segment'             => $structures[ 'id_segment' ],
                        'id_job'                 => $propagatorSegment[ 'id_job' ],
                        'segment_hash'           => $propagatorSegment[ 'segment_hash' ],
                    // UPDATE ONLY THESE FIELDS
                        'translation'            => $propagatorSegment[ 'translation' ],
                        'status'                 => $propagatorSegment[ 'status' ],
                        'translation_date'       => $propagatorSegment[ 'translation_date' ],
                        'autopropagated_from'    => $propagatorSegment[ 'autopropagated_from' ],
                        'serialized_errors_list' => $propagatorSegment[ 'serialized_errors_list' ],
                        'warning'                => $propagatorSegment[ 'warning' ],
                ];

                $chunked_segments = array_chunk( $propagationTotalStruct->getAllToPropagate(), 20, true );

                foreach ( $chunked_segments as $segments ) {

                    $updateValues               = $static_field_values;
                    $propagated_ids_placeholder = [];

                    foreach ( $segments as $i => $segment ) {
                        $propagated_ids_placeholder[]          = ':propagated_id_' . $i;
                        $updateValues[ 'propagated_id_' . $i ] = $segment[ 'id_segment' ];
                    }

                    $propagationSql = "
                            UPDATE segment_translations 
                            SET translation = :translation, 
                                status = :status, 
                                translation_date = :translation_date, 
                                autopropagated_from = :autopropagated_from,
                                serialized_errors_list = :serialized_errors_list,
                                warning = :warning
                            WHERE id_segment != :id_segment 
                              AND id_job = :id_job 
                              AND segment_hash = :segment_hash
                              AND id_segment IN ( " . implode( ",", $propagated_ids_placeholder ) . " )
                        ";

                    $pdo  = Database::obtain()->getConnection();
                    $stmt = $pdo->prepare( $propagationSql );

                    $stmt->execute( $updateValues );

                    $stmt->closeCursor();

                    // update related versions only if the parent translation has changed
                    if ( !empty( $propagationTotalStruct->getPropagatedIdsToUpdateVersion() ) ) {

                        $filteredIds                      = [];
                        $segmentIdsForVersionIncrementMap = $propagationTotalStruct->getPropagatedIdsToUpdateVersion();
                        $segmentsToIncrementMap           = array_filter( $segments, function ( $segment ) use ( $segmentIdsForVersionIncrementMap, &$filteredIds ) {
                            if ( array_key_exists( $segment[ 'id_segment' ], $segmentIdsForVersionIncrementMap ) ) {
                                $filteredIds[] = $segment[ 'id_segment' ];
                                return true;
                            }

                            return false;
                        } );

                        $translationVersionsDao->savePropagationVersions(
                                $propagatorSegment,
                                $structures[ 'id_segment' ],
                                $structures[ 'job' ],
                                $segmentsToIncrementMap,
                        );

                        $increaseVersionSql = "
                            UPDATE segment_translations SET version_number = version_number + 1
                            WHERE id_segment != :id_segment 
                              AND id_job = :id_job 
                              AND segment_hash = :segment_hash
                              AND id_segment IN ( " . implode( ",", $filteredIds ) . " )
                        ";

                        $stmt = $pdo->prepare( $increaseVersionSql );

                        $stmt->execute( [
                                'id_segment'   => $structures[ 'id_segment' ],
                                'id_job'       => $propagatorSegment[ 'id_job' ],
                                'segment_hash' => $propagatorSegment[ 'segment_hash' ]
                        ] );

                        $stmt->closeCursor();

                    }

                }

            } catch ( PDOException $e ) {
                throw new EndQueueException( "Error in propagating Translation: " . $e->getCode() . ": " . $e->getMessage()
                        . "\n"
                        . $propagationSql
                        . "\n"
                        . $increaseVersionSql
                        . "\n"
                        . var_export( $propagatorSegment, true )
                        . "\n"
                        . var_export( $propagationTotalStruct->getPropagatedIds(), true )
                );
            }
        }

    }

    /**
     * Cast to the proper objects from payload
     */
    protected function rebuildObjects( Params $params ): array {
        $paramsArray = $params->toArray();

        return [
                'translationStructTemplate' => new Translations_SegmentTranslationStruct( $paramsArray[ 'translationStructTemplate' ] ),
                'id_segment'                => $params->id_segment,
                'job'                       => new Jobs_JobStruct( $paramsArray[ 'job' ] ),
                'project'                   => new Projects_ProjectStruct( $paramsArray[ 'project' ] ),
                'propagationAnalysis'       => new Propagation_PropagationTotalStruct( $paramsArray[ 'propagationAnalysis' ] ),
                'execute_update'            => $params->execute_update
        ];
    }

}