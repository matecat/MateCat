<?php

namespace Glossary\Blacklist;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class BlacklistStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct
{
    public $id;
    public $chunk;
    public $file_path;
    public $file_name;
    public $target;
    public $uid;

    public function __construct( \Chunks_ChunkStruct $chunk ) {
        parent::__construct([
            'chunk' => $chunk
        ]);
    }

    /**
     *
     * @return array
     * @throws \ReflectionException
     */
    public function toPlainArray() {

        $blacklist = $this->toArray();
        $blacklist['id_job'] = $blacklist['chunk']->id;
        $blacklist['password'] = $blacklist['chunk']->password;
        unset($blacklist['chunk']);

        return $blacklist;
    }
}