<?php

namespace Model\Analysis;

use AMQHandler;
use API\App\Json\Analysis\AnalysisChunk;
use API\App\Json\Analysis\AnalysisFile;
use API\App\Json\Analysis\AnalysisJob;
use API\App\Json\Analysis\AnalysisProject;
use API\App\Json\Analysis\AnalysisProjectSummary;
use API\App\Json\Analysis\MatchConstants;
use API\Commons\Exceptions\AuthenticationError;
use Chunks_ChunkDao;
use Constants_ProjectStatus;
use Exception;
use Exceptions\NotFoundException;
use Exceptions\ValidationError;
use FeatureSet;
use INIT;
use Jobs_JobStruct;
use Langs\LanguageDomains;
use OutsourceTo_OutsourceAvailable;
use Projects_ProjectDao;
use Projects_ProjectStruct;
use Routes;
use Users_UserStruct;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 04/05/15
 * Time: 13.37
 *
 *
 */
abstract class AbstractStatus {

    protected $_data_struct = [];

    /**
     * Carry the result from Executed Controller Action and returned in json format to the Client
     *
     * @var array
     */
    protected $result = [ "errors" => [], "data" => [] ];

    protected $_globals = [];

    protected $total_segments   = 0;
    protected $_resultSet       = [];
    protected $_others_in_queue = 0;
    protected $_project_data    = [];
    protected $status_project   = "";

    protected $_total_init_struct = [
            "TOTAL_PAYABLE" => [ 0, "0" ], "REPETITIONS" => [ 0, "0" ], "MT" => [ 0, "0" ],
            "NEW"           => [ 0, "0" ], "TM_100" => [ 0, "0" ], "TM_100_PUBLIC" => [ 0, "0" ],
            "TM_75_99"      => [ 0, "0" ],
            "TM_75_84"      => [ 0, "0" ], "TM_85_94" => [ 0, "0" ], "TM_95_99" => [ 0, "0" ],
            "TM_50_74"      => [ 0, "0" ], "INTERNAL_MATCHES" => [ 0, "0" ], "ICE" => [ 0, "0" ],
            "NUMBERS_ONLY"  => [ 0, "0" ]
    ];

    protected $featureSet;
    /**
     * @var Projects_ProjectStruct
     */
    protected $project;
    /**
     * @var Users_UserStruct|null
     */
    protected $user;
    /**
     * @var mixed
     */
    protected $subject;

    public function __construct( $_project_data, FeatureSet $features, Users_UserStruct $user = null ) {
        if ( is_null( $user ) ) { // avoid null pointer exception when calling methods on class property user
            $user      = new Users_UserStruct();
            $user->uid = -1;
        }
        $this->user          = $user;
        $this->project       = Projects_ProjectDao::findById( $_project_data[ 0 ][ 'pid' ], 60 * 60 );
        $this->_project_data = $_project_data;
        $this->featureSet    = $features;
    }

    /**
     * @return array
     */
    public function getResult() {
        return $this->result;
    }

    /**
     * @return array
     */
    public function getGlobals() {
        return $this->_globals;
    }

    /**
     * Fetch data for the project
     *
     */
    protected function _fetchProjectData() {

        $this->_resultSet = AnalysisDao::getProjectStatsVolumeAnalysis( $this->project->id );

        try {
            $amqHandler         = new AMQHandler();
            $segmentsBeforeMine = $amqHandler->getActualForQID( $this->project->id );
        } catch ( Exception $e ) {
            $segmentsBeforeMine = null;
        }

        $this->_others_in_queue = ( $segmentsBeforeMine > 0 ? $segmentsBeforeMine : 0 );

        $this->total_segments = count( $this->_resultSet );

        //get status of project
        $this->status_project = $this->_project_data[ 0 ][ 'status_analysis' ];

        $subject_handler = LanguageDomains::getInstance();
        $subjects        = $subject_handler->getEnabledHashMap();
        $this->subject   = $subjects[ $this->_project_data[ 0 ][ 'subject' ] ];

    }

    /**
     * Perform the computation
     *
     * @return $this
     * @throws NotFoundException
     */
    public function fetchData() {

        $this->_fetchProjectData();

        return $this->loadObjects();

    }

    /**
     * @return bool
     * @throws Exception
     */
    protected function isOutsourceEnabled( $targetLang, $id_customer, $idJob ) {

        $outsourceAvailableInfo = $this->featureSet->filter( 'outsourceAvailableInfo', $targetLang, $id_customer, $idJob );

        // if the hook is not triggered by any plugin
        if ( !is_array( $outsourceAvailableInfo ) or empty( $outsourceAvailableInfo ) ) {
            $outsourceAvailableInfo = [
                    'disabled_email'         => false,
                    'custom_payable_rate'    => false,
                    'language_not_supported' => false,
            ];
        }

        return OutsourceTo_OutsourceAvailable::isOutsourceAvailable( $outsourceAvailableInfo );

    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    protected function loadObjects() {

        $target       = null;
        $this->result = $project = new AnalysisProject(
                $this->_project_data[ 0 ][ 'pname' ],
                $this->_project_data[ 0 ][ 'status_analysis' ],
                $this->_project_data[ 0 ][ 'create_date' ],
                $this->subject,
                new AnalysisProjectSummary(
                        $this->_others_in_queue,
                        $this->total_segments,
                        $this->status_project
                )
        );

        $project->setAnalyzeLink( $this->getAnalyzeLink() );

        foreach ( $this->_resultSet as $segInfo ) {

            if ( $project->getSummary()->getTotalFastAnalysis() == 0 and $segInfo[ 'fast_analysis_wc' ] > 0 ) {
                $project->getSummary()->setTotalFastAnalysis( $segInfo[ 'fast_analysis_wc' ] );
            }

            /*
             *  Create & Set objects while iterating
             */
            if ( !isset( $job ) || $job->getId() != $segInfo[ 'jid' ] ) {
                $job = new AnalysisJob( $segInfo[ 'jid' ], $segInfo[ 'source' ], $segInfo[ 'target' ] );
                $project->setJob( $job );
            }

            if ( !isset( $chunk ) || $chunk->getPassword() != $segInfo[ 'jpassword' ] ) {
                $chunkStruct = Chunks_ChunkDao::getByIdAndPassword( $segInfo[ 'jid' ], $segInfo[ 'jpassword' ], 60 * 10 );
                $chunk       = new AnalysisChunk( $chunkStruct, $this->_project_data[ 0 ][ 'pname' ], $this->user );
                $job->setPayableRates( json_decode( $chunkStruct->payable_rates ) );
                $job->setChunk( $chunk );
            }

            // is outsource available?
            if ( $target === null or $segInfo[ 'target' ] !== $target ) {
                $job->setOutsourceAvailable(
                        $this->isOutsourceEnabled( $segInfo[ 'target' ], $segInfo[ 'id_customer' ], $segInfo[ 'jid' ] )
                );
                $target = $segInfo[ 'target' ];
            }

            if ( !isset( $file ) || $file->getId() != $segInfo[ 'id_file' ] || !$chunk->hasFile( $segInfo[ 'id_file' ] ) ) {
                $originalFile = ( !empty( $segInfo[ 'tag_key' ] ) and $segInfo[ 'tag_key' ] === 'original' ) ? $segInfo[ 'tag_value' ] : $segInfo[ 'filename' ];
                $id_file_part = ( !empty( $segInfo[ 'id_file_part' ] ) ) ? (int)$segInfo[ 'id_file_part' ] : null;
                $file         = new AnalysisFile( $segInfo[ 'id_file' ], $id_file_part, $segInfo[ 'filename' ], $originalFile );
                $chunk->setFile( $file );
            }
            // Runtime Initialization Completed

            $matchType = MatchConstants::toExternalMatchTypeValue( $segInfo[ 'match_type' ] ?? 'NEW' );

            // increment file totals
            $file->incrementRaw( $segInfo[ 'raw_word_count' ] );
            $file->incrementEquivalent( $segInfo[ 'eq_word_count' ] );

            // increment single file match
            $match = $file->getMatch( $matchType );
            $match->incrementRaw( $segInfo[ 'raw_word_count' ] );
            $match->incrementEquivalent( $segInfo[ 'eq_word_count' ] );

            //increment chunk summary for the current match type
            $chunkMatchTotal = $chunk->getSummary()->getMatch( $matchType );
            $chunkMatchTotal->incrementRaw( $segInfo[ 'raw_word_count' ] );
            $chunkMatchTotal->incrementEquivalent( $segInfo[ 'eq_word_count' ] );

            // increment job totals
            $job->incrementRaw( $segInfo[ 'raw_word_count' ] );
            $job->incrementEquivalent( $segInfo[ 'eq_word_count' ] );
            $job->incrementIndustry( $segInfo[ 'standard_word_count' ] );

            // increment chunk totals
            $chunk->incrementRaw( $segInfo[ 'raw_word_count' ] );
            $chunk->incrementEquivalent( $segInfo[ 'eq_word_count' ] );
            $chunk->incrementIndustry( $segInfo[ 'standard_word_count' ] );

            // increment project summary
            if ( $segInfo[ 'st_status_analysis' ] == 'DONE' ) {
                $project->getSummary()->incrementAnalyzed();
            }
            $project->getSummary()->incrementRaw( $segInfo[ 'raw_word_count' ] );
            $project->getSummary()->incrementEquivalent( $segInfo[ 'eq_word_count' ] );
            $project->getSummary()->incrementIndustry( $segInfo[ 'standard_word_count' ] );

        }

        if ( $project->getSummary()->getSegmentsAnalyzed() == 0 && in_array( $this->status_project,
                        [
                                Constants_ProjectStatus::STATUS_NEW,
                                Constants_ProjectStatus::STATUS_BUSY
                        ]
                ) ) {

            //Related to an issue in the outsource
            //Here, the Fast analysis was not performed, return the number of raw word count
            //Needed because the "getProjectStatsVolumeAnalysis" query based on segment_translations always returns null
            //( there are no segment_translations )

            foreach ( $this->_project_data as $_job_fallback ) {

                $lang_pair = explode( "|", $_job_fallback[ 'lang_pair' ] );
                $job       = new AnalysisJob( $_job_fallback[ 'jid' ], $lang_pair[ 0 ], $lang_pair[ 1 ] );

                $project->setJob( $job );
                $job->incrementIndustry( round( $_job_fallback[ 'standard_analysis_wc' ] ) );
                $job->incrementEquivalent( round( $_job_fallback[ 'standard_analysis_wc' ] ) );
                $job->incrementRaw( round( $_job_fallback[ 'standard_analysis_wc' ] ) );

                $chunkStruct                = new Jobs_JobStruct();
                $chunkStruct->id            = $_job_fallback[ 'jid' ];
                $chunkStruct->password      = $_job_fallback[ 'jpassword' ];
                $chunkStruct->source        = $lang_pair[ 0 ];
                $chunkStruct->target        = $lang_pair[ 1 ];
                $chunkStruct->payable_rates = $_job_fallback[ 'payable_rates' ];

                $chunk = new AnalysisChunk( $chunkStruct, $this->_project_data[ 0 ][ 'pname' ], $this->user );
                $job->setPayableRates( json_decode( $chunkStruct->payable_rates ) );
                $job->setChunk( $chunk );

            }

            $project->getSummary()->incrementRaw( $this->_project_data[ 0 ][ 'standard_analysis_wc' ] );
            $project->getSummary()->incrementIndustry( $this->_project_data[ 0 ][ 'standard_analysis_wc' ] );
            $project->getSummary()->incrementEquivalent( $this->_project_data[ 0 ][ 'standard_analysis_wc' ] );

            return $this;

        }

        return $this;

    }

    /**
     * @return string
     * @throws Exception
     */
    private function getAnalyzeLink() {
        return Routes::analyze( [
                'project_name' => $this->project->name,
                'id_project'   => $this->project->id,
                'password'     => $this->project->password,
        ] );
    }

}