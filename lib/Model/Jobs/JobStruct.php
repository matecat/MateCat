<?php

use DataAccess\ArrayAccessTrait;
use Exceptions\NotFoundException;
use Outsource\ConfirmationDao;
use Outsource\ConfirmationStruct;
use Outsource\TranslatedConfirmationStruct;
use Translations\WarningDao;
use Translators\JobsTranslatorsDao;
use Translators\JobsTranslatorsStruct;

class Jobs_JobStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, \ArrayAccess {

    use ArrayAccessTrait;

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

    public $standard_analysis_wc;

    /**
     * Column 'completed' cannot be null, moreover it is BIT(1) and
     * PDO does not works well in this case without explicitly
     * tell him that this is an INT.
     * So, we can't set 0 because it will be treated as string, set it to false, it works.
     * @see https://bugs.php.net/bug.php?id=50757
     */
    public $completed = false; //Column 'completed' cannot be null

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

    protected $is_review;


    protected $_sourcePage;

    /**
     *
     * @return array
     */
    public function getTMProps(){
        $projectData = $this->getProject();
        $result = [
                'project_id'   => $projectData->id,
                'project_name' => $projectData->name,
                'job_id'       => $this->id,
        ];
        return $result;
    }

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
     * @throws NotFoundException
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
                throw new NotFoundException( "Vendor id " . $this->_outsource->id_vendor . " not found." );
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
            return $mDao->setCacheTTL( 60 * 60 * 24 * 30 )->allByProjectId( $job->id_project );
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

    /**
     * @return bool
     */
    public function isSplitted() {

        return count($this->getChunks()) > 1;
    }

    /**
     * @param Users_UserStruct $user
     * @param                  $role
     *
     * @return array
     */
    public function getClientKeys( Users_UserStruct $user, $role ){
        $uKModel = new \TmKeyManagement\UserKeysModel( $user, $role );
        return $uKModel->getKeys( $this->tm_keys );
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
     * @return bool
     */
    public function isCanceled() {
        return $this->status == Constants_JobStatus::STATUS_CANCELLED ;
    }

    /**
     * @return bool
     */
    public function isArchived() {
        return $this->status == Constants_JobStatus::STATUS_ARCHIVED ;
    }

    /**
     * @param $is_review
     *
     * @return $this
     */
    public function setIsReview($is_review){
        $this->is_review = $is_review;
        return $this;
    }

    /**
     * @param $_revisionNumber
     */
    public function setSourcePage( $_revisionNumber ){
        $this->_sourcePage = $_revisionNumber;
    }

    /**
     * @return mixed
     */
    public function getSourcePage(){
        return $this->_sourcePage;
    }

    /**
     * @return mixed
     */
    public function getIsReview(){
        return $this->is_review;
    }

    /**
     * @return bool
     */
    public function isSecondPassReview(){
        return $this->is_review  && $this->_sourcePage == 3;
    }

    /**
     * @return bool
     */
    public function wasDeleted() {
        return $this->status_owner === Constants_JobStatus::STATUS_DELETED;
    }
}
