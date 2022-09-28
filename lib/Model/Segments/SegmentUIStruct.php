<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 12/06/19
 * Time: 15.29
 *
 */

namespace Segments;


use DataAccess\ArrayAccessTrait;
use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class SegmentUIStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, \ArrayAccess {

    use ArrayAccessTrait;

    public $jid;
    public $id_file;
    public $id_file_part;
    public $filename;
    public $sid;
    public $segment;
    public $segment_hash;
    public $translation;
    public $ice_locked;
    public $status;
    public $time_to_edit;
    public $parsed_time_to_edit;
    public $warning;
    public $source_chunk_lengths;
    public $target_chunk_lengths;
    public $readonly;
    public $autopropagated_from;
    public $repetitions_in_chunk;
    public $revision_number;
    public $notes;
    public $version_number;
    public $data_ref_map;
    public $context_groups;
    public $metadata;

}