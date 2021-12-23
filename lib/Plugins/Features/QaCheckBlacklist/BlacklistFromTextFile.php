<?php

namespace Features\QaCheckBlacklist;

class BlacklistFromTextFile extends AbstractBlacklist {

    /**
     * Reads the whole file and returns the content.
     *
     * @return string
     */
    public function getContent( ) {
        return file_get_contents($this->file_path);
    }
}