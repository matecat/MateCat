<?php

use DataAccess\ArrayAccessTrait;
use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;
use LQA\ModelDao;
use LQA\ModelStruct;
use Teams\TeamDao;
use Teams\TeamStruct;

class Projects_ProjectStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, ArrayAccess {

    use ArrayAccessTrait;

    public $id ;
    public $password ;
    public $name ;
    public $id_customer ;
    public $id_team ;
    public $create_date ;
    public $id_engine_tm ;
    public $id_engine_mt ;
    public $status_analysis ;
    public $fast_analysis_wc ;
    public $standard_analysis_wc ;
    public $tm_analysis_wc;
    public $remote_ip_address ;
    public $instance_id ;
    public $pretranslate_100 ;
    public $id_qa_model ;
    public $id_assignee ;
    public $due_date;

    public function isAnonymous(){
        return $this->id_customer == 'translated_user';
    }

    /**
     * @return bool
     */
    public function analysisComplete() {
        return
                $this->status_analysis == Constants_ProjectStatus::STATUS_DONE ||
                $this->status_analysis == Constants_ProjectStatus::STATUS_NOT_TO_ANALYZE ;
    }

    /**
     * @param int $ttl
     *
     * @return Jobs_JobStruct[]
     */
    public function getJobs( $ttl = 0 ) {
        return $this->cachable(__function__, $this->id, function($id) use( $ttl ) {
            return Jobs_JobDao::getByProjectId( $id, $ttl );
        });
    }

    /**
     * @return array
     */
    public function getTargetLanguages() {
        return array_map(function(Jobs_JobStruct $job) {
            return $job->target ;
        }, $this->getJobs( 60 * 60 * 24 * 30 ) );
    }

    /**
     * Proxy to set metadata for the current project
     *
     * @param $key
     * @param $value
     *
     * @return bool
     */
    public function setMetadata( $key, $value ) {
        $dao = new Projects_MetadataDao( Database::obtain() );
        return $dao->set( $this->id, $key, $value );
    }

    /**
     *
     * @return array
     */
    public function getMetadataAsKeyValue() {
        $collection = $this->getMetadata();
        $data  = array();
        foreach ($collection as $record ) {
            $data[ $record->key ] = $record->value;
        }
        return $data;
    }


    /**
     * @param $key
     *
     * @return mixed
     */
    public function getMetadataValue($key) {
        $meta = $this->getMetadataAsKeyValue();
        if ( array_key_exists($key, $meta) ) {
            return $meta[$key];
        }
        return null;
    }

    /**
     * @return null|Projects_MetadataStruct[]
     */
    public function getMetadata(){
        return $this->cachable( __function__, $this, function ( $project ) {
            $mDao = new Projects_MetadataDao();
            return $mDao->setCacheTTL( 60 * 60 )->allByProjectId( $project->id );
        } );
    }

    /**
     * @return string|null
     */
    public function getProjectFeatures(){

        return $this->cachable( __function__, $this, function ( Projects_ProjectStruct $pStruct ) {

            $allMetaData = $pStruct->getMetadata();

            foreach( $allMetaData as $metadataStruct ){
                if( $metadataStruct->key == Projects_MetadataDao::FEATURES_KEY ){
                    return $metadataStruct->value;
                }
            }
            return null;

        } );

    }


    public function getRemoteFileServiceName(){

        return $this->cachable( __function__, $this, function () {

            $dao = new Projects_ProjectDao() ;
            return @$dao->setCacheTTL( 60 * 60 * 24 * 7 )->getRemoteFileServiceName( [ $this->id ] )[0] ;

        } );

    }

    /**
     * @return null|TeamStruct
     */
    public function getTeam() {
        if ( is_null( $this->id_team ) ) {
            return null ;
        }
        $dao = new TeamDao() ;
        return $dao->findById( $this->id_team ) ;
    }

    /**
     * WARNING $id_customer could not correspond to the real team/assignee
     *
     * @return Users_UserStruct
     */
    public function getOriginalOwner() {
        return ( new Users_UserDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->getByEmail( $this->id_customer ) ;
    }

    /**
     * @param $feature_code
     *
     * @return bool
     *
     */
    public function isFeatureEnabled( $feature_code ) {
        return in_array($feature_code, $this->getFeaturesSet()->getCodes() );
    }

    /**
     * @return FeatureSet
     */
    public function getFeaturesSet() {
        return $this->cachable(__METHOD__, $this, function( Projects_ProjectStruct $project ) {
            $featureSet = new FeatureSet() ;
            $featureSet->loadForProject( $project ) ;
            return $featureSet ;
        });
    }

    /**
     * @param int $ttl
     *
     * @return Chunks_ChunkStruct[]
     */
    public function getChunks( $ttl = 0 ) {
        return $this->cachable( __METHOD__, $this, function () use ( $ttl ) {
            $dao = new Chunks_ChunkDao( Database::obtain() );
            return $dao->setCacheTTL( $ttl )->getByProjectID( $this->id );
        } );
    }

    public function isMarkedComplete() {
      return Chunks_ChunkCompletionEventDao::isProjectCompleted( $this );
    }

    /**
     * @return mixed|string
     */
    public function getWordCountType() {
        return $this->cachable(__METHOD__, $this->getMetadataValue( Projects_MetadataDao::WORD_COUNT_TYPE_KEY ), function($type) {
            if ( $type == null ) {
                return Projects_MetadataDao::WORD_COUNT_EQUIVALENT;
            } else {
                return $type;
            }
        });
    }

    /**
     * @param float|int $ttl
     *
     * @return ModelStruct
     */
    public function getLqaModel( $ttl = 86400 ) {
        return $this->cachable( __METHOD__, $this->id_qa_model, function ( $id_qa_model ) use ( $ttl ) {
            return ModelDao::findById( $id_qa_model, $ttl );
        } );
    }

    /**
     * Most of the times only one zip per project is uploaded.
     * This method is a shortcut to access the zip file path.
     *
     * TODO Remove this from a struct object!!!
     *
     * @return string the original zip path.
     * @throws Exception
     */
    public function getFirstOriginalZipPath() {

        $fs = FilesStorageFactory::create();
        $jobs = $this->getJobs();
        $files = Files_FileDao::getByJobId($jobs[0]->id);

        $zipName = explode( ZipArchiveExtended::INTERNAL_SEPARATOR, $files[0]->filename );

        if( AbstractFilesStorage::pathinfo_fix( $zipName[0], PATHINFO_EXTENSION ) != 'zip' ){
            return null;
        }

        $zipName = $zipName[0];

        $originalZipPath = $fs->getOriginalZipPath( $this->create_date, $this->id, $zipName );

        if( AbstractFilesStorage::isOnS3() ){
            $params[ 'bucket' ]  = INIT::$AWS_STORAGE_BASE_BUCKET;
            $params[ 'key' ]     = $originalZipPath;
            $params[ 'save_as' ] = "/tmp/" . AbstractFilesStorage::pathinfo_fix( $originalZipPath, PATHINFO_BASENAME );
            $client              = $fs::getStaticS3Client();
            $client->downloadItem( $params );
            $originalZipPath = $params[ 'save_as' ];
        }

        return $originalZipPath ;
    }

    public function hasFeature( $feature_code ) {
        return in_array( $feature_code, $this->getFeaturesSet()->getCodes() ) ;
    }


}
