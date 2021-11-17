<?php

namespace Features\QaCheckBlacklist;

class BlacklistFromTextFile extends AbstractBlacklist {

    /**
     * Reads the whole file and returns the content.
     *
     * @param $file_path
     *
     * @return string
     */
    public function getContent( $file_path ) {
        return file_get_contents($file_path);
    }
}