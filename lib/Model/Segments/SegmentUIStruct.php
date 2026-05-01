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

/** @implements ArrayAccess<string, mixed> */
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
    /** @var list<string|int> */
    public array $parsed_time_to_edit = [];
    public bool $warning = false;
    /** @var list<int>|string */
    public $source_chunk_lengths = '[]';
    /** @var array{len: list<int>, statuses: list<string>}|string */
    public $target_chunk_lengths = '[]';

    public bool $readonly;
    public int $autopropagated_from;
    public int $repetitions_in_chunk;
    public ?int $revision_number = null;
    /** @var ?list<array{id: int, note: string}> */
    public ?array $notes = null;
    public int $version_number;
    /** @var array<string, string>|string */
    public $data_ref_map = '[]';
    public ?ContextStruct $context_groups = null;
    /** @var ?list<array{id_segment: int, meta_key: string, meta_value: mixed}> */
    public ?array $metadata = null;
    public ?string $context_url = null;
    public string $internal_id;
    public bool $icu = false;

}