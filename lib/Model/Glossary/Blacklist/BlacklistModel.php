<?php

namespace Glossary\Blacklist;

class BlacklistModel
{
    public $id;
    public $chunk;
    public $file_path;
    public $file_name;
    public $target;
    public $uid;

    public function __construct( \Chunks_ChunkStruct $chunk ) {
        $this->chunk = $chunk;
    }
}