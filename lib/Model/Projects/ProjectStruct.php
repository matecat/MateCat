<?php

namespace Model\Projects;

use ArrayAccess;
use Constants_ProjectStatus;
use Database;
use FeatureSet;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\ArrayAccessTrait;
use Model\DataAccess\IDaoStruct;
use Model\Jobs\ChunkDao;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ModelDao;
use Model\LQA\ModelStruct;
use Model\RemoteFiles\RemoteFileServiceNameStruct;
use ReflectionException;
use Teams\TeamDao;
use Teams\TeamStruct;

class ProjectStruct extends AbstractDaoSilentStruct implements IDaoStruct, ArrayAccess {

    use ArrayAccessTrait;

    public ?int    $id                   = null;
    public string  $password;
    public string  $name;
    public string  $id_customer;
    public string  $create_date;
    public ?int    $id_engine_tm         = null;
    public ?int    $id_engine_mt         = null;
    public string  $status_analysis;
    public ?int    $fast_analysis_wc     = 0;
    public ?int    $standard_analysis_wc = 0;
    public ?int    $tm_analysis_wc       = 0;
    public string  $remote_ip_address;
    public ?int    $instance_id          = 0;
    public ?int    $pretranslate_100     = 0;
    public ?int    $id_qa_model          = null;
    public ?int    $id_team              = null;
    public ?int    $id_assignee          = null;
    public ?string $due_date             = null;

    /**
     * @return bool
     */
    public function analysisComplete(): bool {
        return
                $this->status_analysis == Constants_ProjectStatus::STATUS_DONE ||
                $this->status_analysis == Constants_ProjectStatus::STATUS_NOT_TO_ANALYZE;
    }

    /**
     * @param int $ttl
     *
     * @return JobStruct[]
     */
    public function getJobs( int $ttl = 0 ): array {
        return $this->cachable( __function__, $this->id, function ( $id ) use ( $ttl ) {
            return JobDao::getByProjectId( $id, $ttl );
        } );
    }

    /**
     * Proxy to set metadata for the current project
     *
     * @param string $key
     * @param string $value
     *
     * @return bool
     * @throws ReflectionException
     */
    public function setMetadata( string $key, string $value ): bool {
        $dao = new MetadataDao( Database::obtain() );

        return $dao->set( $this->id, $key, $value );
    }

    /**
     *
     * @return array
     */
    public function getMetadataAsKeyValue(): array {
        $collection = $this->getMetadata();
        $data       = [];
        foreach ( $collection as $record ) {
            $data[ $record->key ] = $record->value;
        }

        return $data;
    }


    /**
     * @param $key
     *
     * @return ?string
     */
    public function getMetadataValue( $key ): ?string {
        $meta = $this->getMetadataAsKeyValue();
        if ( array_key_exists( $key, $meta ) ) {
            return $meta[ $key ];
        }

        return null;
    }

    /**
     * @return null|MetadataStruct[]
     */
    public function getMetadata(): array {
        return $this->cachable( __function__, $this, function ( $project ) {
            $mDao = new MetadataDao();

            return $mDao->setCacheTTL( 60 * 60 )->allByProjectId( $project->id );
        } );
    }

    /**
     * @return ?RemoteFileServiceNameStruct
     */
    public function getRemoteFileServiceName(): ?RemoteFileServiceNameStruct {

        return $this->cachable( __function__, $this, function () {

            $dao = new ProjectDao();

            /** @var \Model\RemoteFiles\RemoteFileServiceNameStruct[] */
            return $dao->setCacheTTL( 60 * 60 * 24 * 7 )->getRemoteFileServiceName( [ $this->id ] )[ 0 ] ?? null;

        } );

    }

    /**
     * @return null|TeamStruct
     * @throws ReflectionException
     */
    public function getTeam(): ?TeamStruct {
        if ( is_null( $this->id_team ) ) {
            return null;
        }
        $dao = new TeamDao();

        return $dao->findById( $this->id_team );
    }

    /**
     * @param $feature_code
     *
     * @return bool
     *
     */
    public function isFeatureEnabled( $feature_code ): bool {
        return in_array( $feature_code, $this->getFeaturesSet()->getCodes() );
    }

    /**
     * @return FeatureSet
     */
    public function getFeaturesSet(): FeatureSet {
        return $this->cachable( __METHOD__, $this, function ( ProjectStruct $project ) {
            $featureSet = new FeatureSet();
            $featureSet->loadForProject( $project );

            return $featureSet;
        } );
    }

    /**
     * @param int $ttl
     *
     * @return JobStruct[]
     */
    public function getChunks( int $ttl = 0 ): array {
        return $this->cachable( __METHOD__, $this, function () use ( $ttl ) {
            $dao = new ChunkDao( Database::obtain() );

            return $dao->setCacheTTL( $ttl )->getByProjectID( $this->id );
        } );
    }

    /**
     * @return string
     */
    public function getWordCountType(): string {
        return $this->cachable( __METHOD__, $this->getMetadataValue( MetadataDao::WORD_COUNT_TYPE_KEY ), function ( $type ) {
            if ( $type == null ) {
                return MetadataDao::WORD_COUNT_EQUIVALENT;
            } else {
                return $type;
            }
        } );
    }

    /**
     * @param float|int $ttl
     *
     * @return ?ModelStruct
     */
    public function getLqaModel( $ttl = 86400 ): ?ModelStruct {
        return $this->cachable( __METHOD__, $this->id_qa_model, function ( $id_qa_model ) use ( $ttl ) {
            return ModelDao::findById( $id_qa_model, $ttl );
        } );
    }

    public function hasFeature( $feature_code ): bool {
        return in_array( $feature_code, $this->getFeaturesSet()->getCodes() );
    }


}
