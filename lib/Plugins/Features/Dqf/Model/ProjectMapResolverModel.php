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
use Utils;

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

        $this->reload();
    }

    public function reload() {
        $this->records = $this->mappedProjectsDao->getByType( $this->chunk, $this->type ) ;
        return $this ;
    }

    public function archiveInverseType() {
        $currentInverseProjects = $this->mappedProjectsDao
                ->getByType( $this->chunk, $this->getInverseType() );

        foreach( $currentInverseProjects as $project ) {
            /** @var DqfProjectMapStruct $project */
            $project->archive_date = Utils::mysqlTimestamp(time());
            DqfProjectMapDao::updateStruct( $project, ['fields' => [ 'archive_date' ] ] );
        }
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

    /**
     * This method returns the parents to use when creating new projects.
     *
     * When type is `translate`, always use the root project (master or vendor root),
     * otherwise use the latest translation proejcts.
     *
     * @return array
     */
    public function getParents() {
        $projects = [];

        if ( !$this->isTranslate() ) {
            $projects = $this->mappedProjectsDao
                    ->getByType( $this->chunk, $this->getInverseType() ) ;
        }

        if ( empty( $projects ) ) {
            $projects = [
                    $this->mappedProjectsDao->findRootProject( $this->chunk )
            ] ;
        }

        $projects  = static::inSegmentBoundaries(
                $projects,
                $this->chunk->job_first_segment,
                $this->chunk->job_last_segment
        );

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
        return static::inSegmentBoundaries( $this->records, $lowest, $highest ) ;
    }

    public static function inSegmentBoundaries( $records, $lowest, $highest ){
        return array_filter( $records, function( DqfProjectMapStruct $item ) use ( $lowest, $highest ) {
            return
                    ( $item->first_segment >= $lowest && $item->first_segment <= $highest ) || // first is contained
                    ( $item->last_segment  >= $lowest && $item->last_segment  <= $highest ) || // last is contained
                    ( $item->first_segment >= $lowest && $item->last_segment  <= $highest ) || //
                    ( $item->first_segment <= $lowest && $item->last_segment  >= $highest )  ;
        }) ;
    }
}