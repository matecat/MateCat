<?php

use Exceptions\NotFoundError;
use Outsource\ConfirmationDao;
use Outsource\ConfirmationStruct;
use Outsource\TranslatedConfirmationStruct;
use Translations\WarningDao;
use Translators\JobsTranslatorsDao;
use Translators\JobsTranslatorsStruct;

class Jobs_JobStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, \ArrayAccess {
    
    public $id;
    public $password;
    public $id_project;

    public $job_first_segment;
    public $job_last_segment;

    public $source;
    public $target;
    public $tm_keys;

    public $id_translator;
    public $job_type;
    public $total_time_to_edit;
    public $avg_post_editing_effort;
    public $only_private_tm;
    public $last_opened_segment;
    public $id_tms;
    public $id_mt_engine;
    public $create_date;
    public $last_update;
    public $disabled;
    public $owner;
    public $status_owner;
    public $status_translator;
    public $status;
    public $completed = 0; //Column 'completed' cannot be null
    public $new_words;
    public $draft_words;
    public $translated_words;
    public $approved_words;
    public $rejected_words;
    public $subject;
    public $payable_rates;
    public $revision_stats_typing_min;
    public $revision_stats_translations_min;
    public $revision_stats_terminology_min;
    public $revision_stats_language_quality_min;
    public $revision_stats_style_min;
    public $revision_stats_typing_maj;
    public $revision_stats_translations_maj;
    public $revision_stats_terminology_maj;
    public $revision_stats_language_quality_maj;
    public $revision_stats_style_maj;
    public $total_raw_wc;

    /**
     * @var JobsTranslatorsStruct
     */
    protected $_translator;

    /**
     * @var ConfirmationStruct
     */
    protected $_outsource;

    /**
     * @var array
     */
    protected $_openThreads;

    /**
     * @return JobsTranslatorsStruct
     */
    public function getTranslator() {

        $this->_translator = $this->cachable(__METHOD__, $this, function( Jobs_JobStruct $jobStruct ) {
            $jTranslatorsDao = new JobsTranslatorsDao();
            return @$jTranslatorsDao->setCacheTTL( 60 * 60 )->findByJobsStruct( $jobStruct )[ 0 ];
        });

        return $this->_translator;

    }

    /**
     * @return ConfirmationStruct
     * @throws NotFoundError
     */
    public function getOutsource() {

        $this->_outsource = $this->cachable(__METHOD__, $this, function( Jobs_JobStruct $jobStruct ) {
            $outsourceDao = new ConfirmationDao();
            return $outsourceDao->setCacheTTL( 60 * 60 )->getConfirmation( $jobStruct );
        });

        if ( empty( $this->_outsource->id_vendor ) ) return null;

        switch ( $this->_outsource->id_vendor ) {
            case TranslatedConfirmationStruct::VENDOR_ID:
                //Ok Do Nothing
                break;
            default:
                throw new NotFoundError( "Vendor id " . $this->_outsource->id_vendor . " not found." );
                break;
        }

        foreach( $this->_outsource as $k => &$value ){
            if( is_numeric( $value ) ){
                if( $value == (string)(int)$value ){
                    $value = (int)$value;
                } elseif( $value == (string)(float)$value ){
                    $value = (float)$value;
                }
            }
        }

        return $this->_outsource;

    }

    public function getOpenThreadsCount(){

        $this->_openThreads = $this->cachable( __METHOD__, $this, function ( Jobs_JobStruct $jobStruct ) {

            $dao         = new Comments_CommentDao();
            $openThreads = $dao->setCacheTTL( 60 * 10 )->getOpenThreadsForProjects( [ $jobStruct->id_project ] ); //ten minutes cache
            foreach ( $openThreads as $openThread ) {
                if ( $openThread->id_job == $jobStruct->id && $openThread->password == $jobStruct->password ) {
                    return (int)$openThread->count;
                }
            }
            return 0;

        } );

        return $this->_openThreads;

    }

    /**
     * @return null|Projects_MetadataStruct[]
     */
    public function getProjectMetadata(){

        return $this->cachable( __function__, $this, function ( $job ) {
            $mDao = new Projects_MetadataDao();
            return $mDao->setCacheTTL( 60 * 60 )->allByProjectId( $job->id_project );
        } );

    }

    public function getProjectFeatures(){

        return $this->cachable( __function__, $this, function () {

            $allMetaData = $this->getProjectMetadata();

            foreach( $allMetaData as $metadataStruct ){
                if( $metadataStruct->key == Projects_MetadataDao::FEATURES_KEY ){
                    return $metadataStruct->value;
                }
            }
            return null;

        } );

    }

    public function getWarningsCount(){

        return $this->cachable( __function__, $this, function ( $jobStruct ) {
            $dao = new WarningDao() ;
            $warningsCount = @$dao->setCacheTTL( 60 * 10 )->getWarningsByProjectIds( [ $jobStruct->id_project ] ) ;
            $ret = [];
            $ret[ 'warnings_count' ] = 0;
            foreach( $warningsCount as $count ) {
                if ( $count->id_job == $jobStruct->id && $count->password == $jobStruct->password ) {
                    $ret[ 'warnings_count' ] = (int) $count->count;
                    $ret[ 'warning_segments' ] = array_map( function( $id_segment ){ return (int)$id_segment; }, explode( ",", $count->segment_list ) );
                }
            }
            return (object)$ret;
        } );

    }

    /**
     * @return Files_FileStruct[]
     */
    public function getFiles() {
        return Files_FileDao::getByJobId( $this->id );
    }

    /**
     * getProject
     *
     * Returns the project struct, caching the result on the instance to avoid
     * unnecessary queries.
     *
     * @param int $ttl
     *
     * @return \Projects_ProjectStruct
     */
    public function getProject( $ttl = 86400 ) {
        return $this->cachable( __function__, $this, function ( $job ) use ( $ttl ){
            return Projects_ProjectDao::findById( $job->id_project, $ttl );
        } );
    }

    /**
     * @return Translations_SegmentTranslationStruct
     */
    public function findLatestTranslation() {
        $dao = new Translations_SegmentTranslationDao( Database::obtain() );

        return $dao->lastTranslationByJobOrChunk( $this );
    }

    /**
     * @return Chunks_ChunkStruct[]
     */
    public function getChunks() {
        return $this->cachable(__METHOD__, $this, function($obj) {
            return Chunks_ChunkDao::getByJobID( $obj->id ) ;
        }) ;
    }

    public function getOwnerKeys(){

        $tm_keys_json = TmKeyManagement_TmKeyManagement::getOwnerKeys( array( $this->tm_keys ) );
        $tm_keys      = array();
        foreach ( $tm_keys_json as $tm_key_struct ) {
            /**
             * @var $tm_key_struct TmKeyManagement_TmKeyStruct
             */
            $tm_keys[] = array(
                    "key"  => $tm_key_struct->key,
                    "r"    => ( $tm_key_struct->r ) ? 'Lookup' : '&nbsp;',
                    "w"    => ( $tm_key_struct->w ) ? 'Update' : '',
                    "name" => $tm_key_struct->name
            );
        }

        return $tm_keys;

    }

    public function getPeeForTranslatedSegments(){
        $pee = round( ( new Jobs_JobDao() )->setCacheTTL( 60 * 15 )->getPeeStats( $this->id, $this->password )->avg_pee , 2 );
        if( $pee >= 100 ){
            $pee = null;
        }
        return $pee;
    }

    /**
     *
     * @return float
     */
    public function totalWordsCount() {
        return $this->new_words +
        $this->draft_words +
        $this->translated_words +
        $this->approved_words +
        $this->rejected_words;
    }

    /**
     * This method is executed when using isset() or empty() on objects implementing ArrayAccess.
     *
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists( $offset ) {
        return property_exists( $this, $offset );
    }

    /**
     * @param mixed $offset
     *
     * @returns mixed
     */
    public function offsetGet( $offset ) {
        return $this->__get( $offset );
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet( $offset, $value ) {
        $this->__set( $offset, $value );
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset( $offset ) {
        $this->__set( $offset, null );
    }

}
