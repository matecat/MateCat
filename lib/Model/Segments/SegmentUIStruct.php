<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 12/06/19
 * Time: 15.29
 *
 */

namespace Model\Segments;


use ArrayAccess;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\ArrayAccessTrait;
use Model\DataAccess\IDaoStruct;

class SegmentUIStruct extends AbstractDaoSilentStruct implements IDaoStruct, ArrayAccess
{

    use ArrayAccessTrait;

    public string $jid;
    public int $id_file;
    public ?int $id_file_part = null;
    public string $filename;
    public string $sid;
    public string $segment;
    public string $segment_hash;
    public ?string $translation = null;
    public bool $ice_locked;
    public string $status;
    public int $time_to_edit;
    public array $parsed_time_to_edit = [];
    public bool $warning = false;
    /**
     * @var array|string
     */
    public $source_chunk_lengths = '[]';
    /**
     * @var array|string
     */
    public $target_chunk_lengths = '[]';

    public bool $readonly;
    public int $autopropagated_from;
    public int $repetitions_in_chunk;
    public ?int $revision_number = null;
    public ?array $notes = null;
    public int $version_number;
    /**
     * @var array|string
     */
    public $data_ref_map = '[]';
    /**
     * @var ?ContextStruct
     */
    public ?ContextStruct $context_groups = null;
    public ?array $metadata = null;
    public string $internal_id;

}