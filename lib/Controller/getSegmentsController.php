<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
class getSegmentsController extends ajaxController {

    private $data = array();
    private $cid = "";
    private $jid = "";
    private $tid = "";
    private $password = "";
    private $source = "";
    private $pname = "";
    private $err = '';
    private $create_date = "";
    private $filetype_handler = null;
    private $start_from = 0;
    private $page = 0;

    /**
     * @var Chunks_ChunkStruct
     */
    private $job;

    /**
     * @var Projects_ProjectStruct
     */
    private $project ;

    /**
     * @var FeatureSet
     */
    private $feature_set ;

    private $segment_notes ;


    public function __construct() {

        parent::__construct();

        $filterArgs = array(
            'jid'         => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'step'        => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'segment' => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'password'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'where'       => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI Test scripts
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->jid         = (int)$__postInput[ 'jid' ];
        $this->step        = $__postInput[ 'step' ];
        $this->ref_segment = $__postInput[ 'segment' ];
        $this->password    = $__postInput[ 'password' ];
        $this->where       = $__postInput[ 'where' ];

    }

    public function doAction() {

        //get Job Infos
        $job_data = getJobData( $this->jid );

        $pCheck = new AjaxPasswordCheck();
        //check for Password correctness
        if( !$pCheck->grantJobAccessByJobData( $job_data, $this->password ) ){
            $this->result['errors'][] = array("code" => -10, "message" => "wrong password");
            return;
        }

        $this->job = Chunks_ChunkDao::getByIdAndPassword( $this->jid, $this->password );
        $this->project = $this->job->getProject();
        $this->feature_set = FeatureSet::fromIdCustomer($this->project->id_customer);

		$lang_handler = Langs_Languages::getInstance();

		if ($this->ref_segment == '') {
			$this->ref_segment = 0;
		}


        $data = getMoreSegments(
                $this->jid, $this->password, $this->step,
                $this->ref_segment, $this->where,
                $this->getOptionalQueryFields()
        );

        $this->prepareNotes( $data );

		foreach ($data as $i => $seg) {

            if ($this->where == 'before') {
                if (((float) $seg['sid']) >= ((float) $this->ref_segment)) {
                    break;
                }
            }

			if (empty($this->pname)) {
				$this->pname = $seg['pname'];
			}

			if (empty($this->last_opened_segment)) {
				$this->last_opened_segment = $seg['last_opened_segment'];
			}

			if (empty($this->cid)) {
				$this->cid = $seg['cid'];
			}

			if (empty($this->pid)) {
				$this->pid = $seg['pid'];
			}

			if (empty($this->tid)) {
				$this->tid = $seg['tid'];
			}

			if (empty($this->create_date)) {
				$this->create_date = $seg['create_date'];
			}

			if (empty($this->source_code)) {
				$this->source_code = $seg['source'];
			}

			if (empty($this->target_code)) {
				$this->target_code = $seg['target'];
			}

            if (empty($this->source)) {
                $s = explode("-", $seg['source']);
                $source = strtoupper($s[0]);
                $this->source = $source;
            }

            if (empty($this->target)) {
                $t = explode("-", $seg['target']);
                $target = strtoupper($t[0]);
                $this->target = $target;
            }

            if (empty($this->err)) {
                $this->err = $seg['serialized_errors_list'];
            }

			$id_file = $seg['id_file'];

			if ( !isset($this->data["$id_file"]) ) {
                $this->data["$id_file"]['jid'] = $seg['jid'];
                $this->data["$id_file"]["filename"] = ZipArchiveExtended::getFileName($seg['filename']);
                $this->data["$id_file"]["mime_type"] = $seg['mime_type'];
                $this->data["$id_file"]['source'] = $lang_handler->getLocalizedName($seg['source']);
                $this->data["$id_file"]['target'] = $lang_handler->getLocalizedName($seg['target']);
                $this->data["$id_file"]['source_code'] = $seg['source'];
                $this->data["$id_file"]['target_code'] = $seg['target'];
                $this->data["$id_file"]['segments'] = array();
            }

            $seg = $this->feature_set->filter('filter_get_segments_segment_data', $seg) ;

            unset($seg['id_file']);
            unset($seg['source']);
            unset($seg['target']);
            unset($seg['source_code']);
            unset($seg['target_code']);
            unset($seg['mime_type']);
            unset($seg['filename']);
            unset($seg['jid']);
            unset($seg['pid']);
            unset($seg['cid']);
            unset($seg['tid']);
            unset($seg['pname']);
            unset($seg['create_date']);
            unset($seg['id_segment_end']);
            unset($seg['id_segment_start']);
            unset($seg['serialized_errors_list']);

            $seg['parsed_time_to_edit'] = CatUtils::parse_time_to_edit($seg['time_to_edit']);

            ( $seg['source_chunk_lengths'] === null ? $seg['source_chunk_lengths'] = '[]' : null );
            ( $seg['target_chunk_lengths'] === null ? $seg['target_chunk_lengths'] = '{"len":[0],"statuses":["DRAFT"]}' : null );
            $seg['source_chunk_lengths'] = json_decode( $seg['source_chunk_lengths'], true );
            $seg['target_chunk_lengths'] = json_decode( $seg['target_chunk_lengths'], true );

            $seg['segment'] = CatUtils::rawxliff2view( CatUtils::reApplySegmentSplit(
                $seg['segment'] , $seg['source_chunk_lengths'] )
            );

            $seg['translation'] = CatUtils::rawxliff2view( CatUtils::reApplySegmentSplit(
                $seg['translation'] , $seg['target_chunk_lengths'][ 'len' ] )
            );

            $this->attachNotes( $seg );

            $this->data["$id_file"]['segments'][] = $seg;
        }

        $this->result['data']['files'] = $this->data;

        $this->result['data']['where'] = $this->where;
    }


    private function getOptionalQueryFields() {
        $feature = $this->job->getProject()->getOwnerFeature('translation_versions');
        $options = array();

        if ( $feature ) {
            $options['optional_fields'] = array('st.version_number');
        }

        $options = $this->feature_set->filter('filter_get_segments_optional_fields', $options);

        return $options;
    }

    private function attachNotes( &$segment ) {
        $segment['notes'] = @$this->segment_notes[ (int) $segment['sid'] ] ;
    }

    private function prepareNotes( $segments ) {
        if ( ! empty( $segments[0] ) ) {
            $start = $segments[0]['sid'];
            $last = end($segments);
            $stop = $last['sid'];

            $this->segment_notes = Segments_SegmentNoteDao::getAggregatedBySegmentIdInInterval($start, $stop);
        }

    }


}
