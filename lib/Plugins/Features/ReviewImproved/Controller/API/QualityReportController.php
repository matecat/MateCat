<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/26/16
 * Time: 2:32 PM
 */

namespace Features\ReviewImproved\Controller\API;

use API\V2\Validators\ChunkPasswordValidator;
use API\V2\KleinController;
use Chunks_ChunkStruct;
use Projects_ProjectStruct;
use Features\ReviewImproved\Model\ArchivedQualityReportDao;
use Features\ReviewImproved\Model\QualityReportModel ;
use CatUtils;

class QualityReportController extends KleinController
{

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk;

    /**
     * @var Projects_ProjectStruct
     */
    protected $project;

    /**
     * @param Chunks_ChunkStruct $chunk
     *
     * @return $this
     */
    public function setChunk( $chunk ) {
        $this->chunk = $chunk;

        return $this;
    }

    private $model ;

    public function show() {
        $this->model = new QualityReportModel( $this->chunk );
        $this->model->setDateFormat('c');

        $this->response->json( array(
                'quality-report' => $this->model->getStructure()
        ));
    }

    private function getOptionalQueryFields() {
        $feature = $this->chunk->getProject()->isFeatureEnabled('translation_versions');
        $options = array();

        if ( $feature ) {
            $options['optional_fields'] = array('st.version_number');
        }

        $options['optional_fields'][] = "st.suggestion_source";
        $options['optional_fields'][] = "st.suggestion";
        $options['optional_fields'][] = "st.edit_distance";


        $options = $this->featureSet->filter('filter_get_segments_optional_fields', $options);

        return $options;
    }

    public function segments() {

        $this->project    = $this->chunk->getProject();

        $this->featureSet->loadForProject( $this->project ) ;

        $lang_handler = \Langs_Languages::getInstance();

        if ($this->ref_segment == '') {
            $this->ref_segment = 0;
        }


        $data = getMoreSegments(
                $this->chunk->id, $this->chunk->password, 50,
                $this->ref_segment, "after",
                $this->getOptionalQueryFields()
        );

        foreach ($data as $i => $seg) {


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
                $this->data["$id_file"]["filename"] = \ZipArchiveExtended::getFileName($seg['filename']);
                $this->data["$id_file"]["mime_type"] = $seg['mime_type'];
                $this->data["$id_file"]['source'] = $lang_handler->getLocalizedName($seg['source']);
                $this->data["$id_file"]['target'] = $lang_handler->getLocalizedName($seg['target']);
                $this->data["$id_file"]['source_code'] = $seg['source'];
                $this->data["$id_file"]['target_code'] = $seg['target'];
                $this->data["$id_file"]['segments'] = array();
            }

            $seg = $this->featureSet->filter('filter_get_segments_segment_data', $seg) ;

            $edit_log_attr = ['id', 'source', 'internal_id', 'translation', 'time_to_edit', 'suggestion', 'suggestions_array', 'suggestion_source', 'suggestion_match', 'suggestion_position', 'segment_hash', 'mt_qe', 'id_translator', 'job_id', 'job_source', 'job_target', 'raw_word_count', 'proj_name', 'secs_per_word', 'warnings', 'match_type', 'locked', 'uid', 'email'];

            $edit_log_array = [];
            foreach($seg as $key => $value){
                if(in_array($key, $edit_log_attr)){
                    $edit_log_array[$key] = $value;
                }
            }

            $edit_log_array['source'] = $seg['segment'];

            $displaySeg = new \EditLog_EditLogSegmentClientStruct(
                    $edit_log_array
            );

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


            $seg['pee'] = $displaySeg->getPEE();
            $seg['ice_modified'] = $displaySeg->isICEModified();
            $seg['secs_per_word'] = $displaySeg->getSecsPerWord();
            /*$displaySeg->getWarning();
            $seg['warnings'] = $displaySeg->warnings;*/

            $this->data["$id_file"]['segments'][] = $seg;

        }

        $this->result['data']['files'] = $this->data;

        //$this->result['data']['where'] = $this->where;
        $this->response->json($this->result);
    }

    public function versions() {
        $dao = new ArchivedQualityReportDao();
        $versions = $dao->getAllByChunk( $this->chunk ) ;
        $response = array();

        foreach( $versions as $version ) {
            $response[] = array(
                    'id' => (int) $version->id,
                    'version_number' => (int) $version->version,
                    'created_at' => \Utils::api_timestamp( $version->create_date ),
                    'quality-report' => json_decode( $version->quality_report )
            ) ;
        }

        $this->response->json( array('versions' => $response ) ) ;

    }

    protected function afterConstruct() {
        $Validator = new ChunkPasswordValidator( $this ) ;
        $Controller = $this;
        $Validator->onSuccess( function () use ( $Validator, $Controller ) {
            $Controller->setChunk( $Validator->getChunk() );
        } );
        $this->appendValidator( $Validator );
    }

}