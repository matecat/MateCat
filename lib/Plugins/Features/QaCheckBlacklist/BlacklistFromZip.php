<?php

namespace Features\QaCheckBlacklist;

class BlacklistFromZip extends AbstractBlacklist {

    /**
     * Reads the whole file and returns the content.
     *
     * @return string
     */
    public function getContent() {
        $zip = new \ZipArchive();
        $zip->open( $this->file_path );
        $content = $zip->getFromName( '__meta/blacklist.txt' );
        $zip->close();

        return $content;
    }
}