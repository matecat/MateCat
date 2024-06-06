<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

use Matecat\SubFiltering\MateCatFilter;
use Segments\ContextGroupDao;

class getSegmentsController extends ajaxController {

    const DEFAULT_PER_PAGE = 100;
    const MAX_PER_PAGE = 200;

    private $where       = 'after';
    private $step        = self::DEFAULT_PER_PAGE;
    private $data        = [];
    private $cid         = "";
    private $jid         = "";
    private $tid         = "";
    private $password    = "";
    private $source      = "";
    private $pname       = "";
    private $create_date = "";

    /**
     * @var Chunks_ChunkStruct
     */
    private $job;

    /**
     * @var Projects_ProjectStruct
     */
    private $project;

    private $segment_notes;

    public function __construct() {

        parent::__construct();

        $filterArgs = [
                'jid'      => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'step'     => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'segment'  => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password' => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ],
                'where'    => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ],
        ];

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI Test scripts
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->jid        = $__postInput[ 'jid' ];
        $this->step       = $__postInput[ 'step' ];
        $this->id_segment = $__postInput[ 'segment' ];
        $this->password   = $__postInput[ 'password' ];
        $this->where      = $__postInput[ 'where' ];

        if( $this->step > self::MAX_PER_PAGE ) {
            $this->step = self::MAX_PER_PAGE;
        }

    }

    public function doAction() {

        $this->job     = Chunks_ChunkDao::getByIdAndPassword( $this->jid, $this->password );
        $this->project = $this->job->getProject();

        $featureSet = $this->getFeatureSet();

        $featureSet->loadForProject( $this->project );

        $lang_handler = Langs_Languages::getInstance();

        $this->parseIDSegment();

        if ( $this->id_segment == '' ) {
            $this->id_segment = 0;
        }

        $sDao = new Segments_SegmentDao();
        $data = $sDao->getPaginationSegments(
                $this->job,
                $this->step,
                $this->id_segment,
                $this->where,
                [
                        'optional_fields' => [
                                'st.edit_distance',
                                'st.version_number'
                        ]
                ]
        );

        $this->prepareNotes( $data );
        $contexts = $this->getContextGroups( $data );

        $this->pname       = $this->project->name;
        $this->cid         = $this->project->id_customer;
        $this->tid         = $this->job->id_translator;
        $this->create_date = $this->project->create_date;

        foreach ( $data as $i => $seg ) {

            $id_file = $seg[ 'id_file' ];

            if ( !isset( $this->data[ $id_file ] ) ) {
                $this->data[ $id_file ][ 'jid' ]         = $seg[ 'jid' ];
                $this->data[ $id_file ][ "filename" ]    = ZipArchiveExtended::getFileName( $seg[ 'filename' ] );
                $this->data[ $id_file ][ 'source' ]      = $lang_handler->getLocalizedName( $this->job->source );
                $this->data[ $id_file ][ 'target' ]      = $lang_handler->getLocalizedName( $this->job->target );
                $this->data[ $id_file ][ 'source_code' ] = $this->job->source;
                $this->data[ $id_file ][ 'target_code' ] = $this->job->target;
                $this->data[ $id_file ][ 'segments' ]    = [];
            }

            $seg = $featureSet->filter( 'filter_get_segments_segment_data', $seg );

            $seg[ 'parsed_time_to_edit' ] = CatUtils::parse_time_to_edit( $seg[ 'time_to_edit' ] );

            ( $seg[ 'source_chunk_lengths' ] === null ? $seg[ 'source_chunk_lengths' ] = '[]' : null );
            ( $seg[ 'target_chunk_lengths' ] === null ? $seg[ 'target_chunk_lengths' ] = '{"len":[0],"statuses":["DRAFT"]}' : null );
            $seg[ 'source_chunk_lengths' ] = json_decode( $seg[ 'source_chunk_lengths' ], true );
            $seg[ 'target_chunk_lengths' ] = json_decode( $seg[ 'target_chunk_lengths' ], true );

            // inject original data ref map (FOR XLIFF 2.0)
            $data_ref_map          = json_decode( $seg[ 'data_ref_map' ], true );
            $seg[ 'data_ref_map' ] = $data_ref_map;

            $Filter = MateCatFilter::getInstance( $featureSet, $this->job->source, $this->job->target, null !== $data_ref_map ? $data_ref_map : [] );

            $seg[ 'segment' ] = $Filter->fromLayer0ToLayer1(
                    CatUtils::reApplySegmentSplit( $seg[ 'segment' ], $seg[ 'source_chunk_lengths' ] )
            );

            $seg[ 'translation' ] = $Filter->fromLayer0ToLayer1(
                    CatUtils::reApplySegmentSplit( $seg[ 'translation' ], $seg[ 'target_chunk_lengths' ][ 'len' ] )
            );

            $seg[ 'translation' ] = $Filter->fromLayer1ToLayer2( $Filter->realignIDInLayer1( $seg[ 'segment' ], $seg[ 'translation' ] ) );
            $seg[ 'segment' ]     = $Filter->fromLayer1ToLayer2( $seg[ 'segment' ] );

            $seg[ 'metadata' ] = Segments_SegmentMetadataDao::getAll( $seg[ 'sid' ] );

            $this->attachNotes( $seg );
            $this->attachContexts( $seg, $contexts );

            $this->data[ $id_file ][ 'segments' ][] = $seg;
        }

        $this->result[ 'data' ][ 'files' ] = $this->data;
        $this->result[ 'data' ][ 'where' ] = $this->where;

        $this->result[ 'data' ] = $featureSet->filter( 'filterGetSegmentsResult', $this->result[ 'data' ], $this->job );
    }

    private function attachNotes( &$segment ) {
        $segment[ 'notes' ] = isset( $this->segment_notes[ (int)$segment[ 'sid' ] ] ) ? $this->segment_notes[ (int)$segment[ 'sid' ] ] : null;
    }

    private function prepareNotes( $segments ) {
        if ( !empty( $segments[ 0 ] ) ) {
            $start = $segments[ 0 ][ 'sid' ];
            $last  = end( $segments );
            $stop  = $last[ 'sid' ];
            if ( $this->featureSet->filter( 'prepareAllNotes', false ) ) {
                $this->segment_notes = Segments_SegmentNoteDao::getAllAggregatedBySegmentIdInInterval( $start, $stop );
                foreach ( $this->segment_notes as $k => $noteObj ) {
                    $this->segment_notes[ $k ][ 0 ][ 'json' ] = json_decode( $noteObj[ 0 ][ 'json' ], true );
                }
                $this->segment_notes = $this->featureSet->filter( 'processExtractedJsonNotes', $this->segment_notes );
            } else {
                $this->segment_notes = Segments_SegmentNoteDao::getAggregatedBySegmentIdInInterval( $start, $stop );
            }

        }

    }

    private function getContextGroups( $segments ) {
        if ( !empty( $segments[ 0 ] ) ) {
            $start = $segments[ 0 ][ 'sid' ];
            $last  = end( $segments );
            $stop  = $last[ 'sid' ];

            return ( new ContextGroupDao() )->getBySIDRange( $start, $stop );
        }
    }

    private function attachContexts( &$segment, $contexts ) {
        $segment[ 'context_groups' ] = isset( $contexts[ (int)$segment[ 'sid' ] ] ) ? $contexts[ (int)$segment[ 'sid' ] ] : null;
    }


}
