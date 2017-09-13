<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/09/2017
 * Time: 14:58
 */

namespace Features\Dqf\Model;


use Chunks_ChunkStruct;
use DomainException;

class ProjectMapResolverModel {

    /** @var Chunks_ChunkStruct  */
    protected $chunk ;

    protected $records ;

    protected $type ;

    /**
     * @var DqfProjectMapDao
     */
    protected $mappedProjectsDao ;

    public function __construct( Chunks_ChunkStruct $chunk, $type ) {
        $this->chunk = $chunk  ;
        if ( !in_array($type, [
                DqfProjectMapDao::PROJECT_TYPE_TRANSLATE ,
                DqfProjectMapDao::PROJECT_TYPE_REVISE
        ] ) ) {
            throw new DomainException('Type is invalid ' . $type );
        }

        $this->type = $type;
        $this->mappedProjectsDao = new DqfProjectMapDao();
        $this->records = $this->mappedProjectsDao
                ->getCurrentByType( $this->chunk, $this->type ) ;
    }

    /**
     * @return array|DqfProjectMapStruct[]
     */
    public function getMappedProjects() {
        return $this->getCurrentInSegmentIdBoundaries(
                $this->chunk->job_first_segment,
                $this->chunk->job_last_segment
        ) ;
    }

    public function getParents() {
        $projects = $this->mappedProjectsDao
                ->getCurrentByType( $this->chunk, $this->getInverseType() ) ;

        if ( empty( $projects ) ) {
            $projects = [
                    $this->mappedProjectsDao->findRootProject( $this->chunk )
            ] ;
        }

        return $projects ;

    }

    protected function getInverseType() {
        if ( $this->isTranslate() ) {
            return DqfProjectMapDao::PROJECT_TYPE_REVISE ;
        }
        else {
            return DqfProjectMapDao::PROJECT_TYPE_TRANSLATE ;
        }
    }

    protected function isTranslate() {
        return $this->type == DqfProjectMapDao::PROJECT_TYPE_TRANSLATE ;
    }

    /**
     * This method returns all dqf map records that are related to this chunk
     *
     * @param $lowest
     * @param $highest
     *
     * @return array|DqfProjectMapStruct[]
     */
    public function getCurrentInSegmentIdBoundaries( $lowest, $highest ) {
        $records = array_filter( $this->records, function( DqfProjectMapStruct $item ) use ( $lowest, $highest ) {
            return
                    ( $item->first_segment >= $lowest && $item->first_segment <= $highest ) || // first is contained
                    ( $item->last_segment  >= $lowest && $item->last_segment  <= $highest ) || // last is contained
                    ( $item->first_segment >= $lowest && $item->last_segment  <= $highest ) || //
                    ( $item->first_segment <= $lowest && $item->last_segment  >= $highest )  ;
        }) ;
        return $records ;
    }
}