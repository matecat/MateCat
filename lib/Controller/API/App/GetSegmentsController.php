<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use CatUtils;
use Chunks_ChunkDao;
use Exception;
use InvalidArgumentException;
use Langs\Languages;
use Matecat\SubFiltering\MateCatFilter;
use Segments\ContextGroupDao;
use Segments_SegmentDao;
use Segments_SegmentMetadataDao;
use Segments_SegmentNoteDao;
use ZipArchiveExtended;

class GetSegmentsController extends KleinController {

    const DEFAULT_PER_PAGE = 100;
    const MAX_PER_PAGE = 200;

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function segments()
    {
        try {
            $request = $this->validateTheRequest();
            $jid = $request['jid'];
            $step = $request['step'];
            $id_segment = $request['id_segment'];
            $password = $request['password'];
            $where = $request['where'];

            $job = Chunks_ChunkDao::getByIdAndPassword( $jid, $password );

            if($job === null){
                throw new Exception("Job not found");
            }

            $project = $job->getProject();
            $featureSet = $this->getFeatureSet();
            $featureSet->loadForProject( $project );
            $lang_handler = Languages::getInstance();

            $parsedIdSegment = $this->parseIDSegment($id_segment);

            if ( $parsedIdSegment['id_segment'] == '' ) {
                $parsedIdSegment['id_segment'] = 0;
            }

            $sDao = new Segments_SegmentDao();
            $data = $sDao->getPaginationSegments(
                $job,
                $step,
                $parsedIdSegment['id_segment'],
                $where,
                [
                    'optional_fields' => [
                        'st.edit_distance',
                        'st.version_number'
                    ]
                ]
            );

            $segment_notes = $this->prepareNotes( $data );
            $contexts = $this->getContextGroups( $data );
            $res = [];

            foreach ( $data as $i => $seg ) {

                $id_file = $seg[ 'id_file' ];

                if ( !isset( $res[ $id_file ] ) ) {
                    $res[ $id_file ][ 'jid' ]         = $seg[ 'jid' ];
                    $res[ $id_file ][ "filename" ]    = ZipArchiveExtended::getFileName( $seg[ 'filename' ] );
                    $res[ $id_file ][ 'source' ]      = $lang_handler->getLocalizedName( $job->source );
                    $res[ $id_file ][ 'target' ]      = $lang_handler->getLocalizedName( $job->target );
                    $res[ $id_file ][ 'source_code' ] = $job->source;
                    $res[ $id_file ][ 'target_code' ] = $job->target;
                    $res[ $id_file ][ 'segments' ]    = [];
                }

                if ( isset( $seg[ 'edit_distance' ] ) ) {
                    $seg[ 'edit_distance' ] = round( $seg[ 'edit_distance' ] / 1000, 2 );
                } else {
                    $seg[ 'edit_distance' ] = 0;
                }

                $seg[ 'parsed_time_to_edit' ] = CatUtils::parse_time_to_edit( $seg[ 'time_to_edit' ] );

                ( $seg[ 'source_chunk_lengths' ] === null ? $seg[ 'source_chunk_lengths' ] = '[]' : null );
                ( $seg[ 'target_chunk_lengths' ] === null ? $seg[ 'target_chunk_lengths' ] = '{"len":[0],"statuses":["DRAFT"]}' : null );
                $seg[ 'source_chunk_lengths' ] = json_decode( $seg[ 'source_chunk_lengths' ], true );
                $seg[ 'target_chunk_lengths' ] = json_decode( $seg[ 'target_chunk_lengths' ], true );

                // inject original data ref map (FOR XLIFF 2.0)
                $data_ref_map          = json_decode( $seg[ 'data_ref_map' ], true );
                $seg[ 'data_ref_map' ] = $data_ref_map;

                $Filter = MateCatFilter::getInstance( $featureSet, $job->source, $job->target, null !== $data_ref_map ? $data_ref_map : [] );

                $seg[ 'segment' ] = $Filter->fromLayer0ToLayer1(
                    CatUtils::reApplySegmentSplit( $seg[ 'segment' ], $seg[ 'source_chunk_lengths' ] )
                );

                $seg[ 'translation' ] = $Filter->fromLayer0ToLayer1(
                    CatUtils::reApplySegmentSplit( $seg[ 'translation' ], $seg[ 'target_chunk_lengths' ][ 'len' ] )
                );

                $seg[ 'translation' ] = $Filter->fromLayer1ToLayer2( $Filter->realignIDInLayer1( $seg[ 'segment' ], $seg[ 'translation' ] ) );
                $seg[ 'segment' ]     = $Filter->fromLayer1ToLayer2( $seg[ 'segment' ] );

                $seg[ 'metadata' ] = Segments_SegmentMetadataDao::getAll( $seg[ 'sid' ] );

                $this->attachNotes( $seg, $segment_notes );
                $this->attachContexts( $seg, $contexts );

                $res[ $id_file ][ 'segments' ][] = $seg;
            }

            $result = [
                'errors' => [],
            ];

            $result[ 'data' ][ 'files' ] = $res;
            $result[ 'data' ][ 'where' ] = $where;
            $result[ 'data' ] = $featureSet->filter( 'filterGetSegmentsResult', $result[ 'data' ], $job );

            return $this->response->json($result);

        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }

    /**
     * @return array
     */
    private function validateTheRequest(): array
    {
        $jid = filter_var( $this->request->param( 'jid' ), FILTER_SANITIZE_NUMBER_INT );
        $step = filter_var( $this->request->param( 'step' ), FILTER_SANITIZE_NUMBER_INT );
        $id_segment = filter_var( $this->request->param( 'segment' ), FILTER_SANITIZE_NUMBER_INT );
        $password = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW ] );
        $where = filter_var( $this->request->param( 'where' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW ] );

        if ( empty( $jid) ) {
            throw new InvalidArgumentException("No id job provided", -1);
        }

        if ( empty( $password ) ) {
            throw new InvalidArgumentException("No job password provided", -2);
        }

        if( empty($id_segment) ){
            throw new InvalidArgumentException("No is segment provided", -3);
        }

        if( $step > self::MAX_PER_PAGE ) {
            $step = self::MAX_PER_PAGE;
        }

        return [
            'jid' => $jid,
            'id_segment' => $id_segment,
            'password' => $password,
            'where' => $where,
            'step' => $step,
        ];
    }

    /**
     * @param $segment
     * @param $segment_notes
     */
    private function attachNotes( &$segment, $segment_notes ) {
        $segment[ 'notes' ] = isset( $segment_notes[ (int)$segment[ 'sid' ] ] ) ? $segment_notes[ (int)$segment[ 'sid' ] ] : null;
    }

    /**
     * @param $segment
     * @param $contexts
     */
    private function attachContexts( &$segment, $contexts ) {
        $segment[ 'context_groups' ] = isset( $contexts[ (int)$segment[ 'sid' ] ] ) ? $contexts[ (int)$segment[ 'sid' ] ] : null;
    }

    /**
     * @param $segments
     * @return array|mixed
     * @throws \API\Commons\Exceptions\AuthenticationError
     * @throws \Exceptions\NotFoundException
     * @throws \Exceptions\ValidationError
     * @throws \TaskRunner\Exceptions\EndQueueException
     * @throws \TaskRunner\Exceptions\ReQueueException
     */
    private function prepareNotes( $segments )
    {
        if ( !empty( $segments[ 0 ] ) ) {
            $start = $segments[ 0 ][ 'sid' ];
            $last  = end( $segments );
            $stop  = $last[ 'sid' ];

            if ( $this->featureSet->filter( 'prepareAllNotes', false ) ) {
                $segment_notes = Segments_SegmentNoteDao::getAllAggregatedBySegmentIdInInterval( $start, $stop );
                foreach ( $segment_notes as $k => $noteObj ) {
                    $segment_notes[ $k ][ 0 ][ 'json' ] = json_decode( $noteObj[ 0 ][ 'json' ], true );
                }

                return $this->featureSet->filter( 'processExtractedJsonNotes', $segment_notes );
            }

            return Segments_SegmentNoteDao::getAggregatedBySegmentIdInInterval( $start, $stop );
        }
    }

    /**
     * @param $segments
     * @return array
     */
    private function getContextGroups( $segments )
    {
        if ( !empty( $segments[ 0 ] ) ) {
            $start = $segments[ 0 ][ 'sid' ];
            $last  = end( $segments );
            $stop  = $last[ 'sid' ];

            return ( new ContextGroupDao() )->getBySIDRange( $start, $stop );
        }
    }
}