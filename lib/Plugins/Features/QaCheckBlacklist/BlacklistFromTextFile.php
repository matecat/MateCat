<?php

namespace Features\QaCheckBlacklist;

class BlacklistFromTextFile extends AbstractBlacklist {

    /**
     * @return string
     */
    public function getContent( ) {
        return $this->content;
    }
}