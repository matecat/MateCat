<?php

namespace Features\QaCheckBlacklist;

class BlacklistFromZip extends AbstractBlacklist {

    /**
     * Reads the whole file and returns the content.
     *
     * @param $file_path
     *
     * @return string
     */
    public function getContent( $file_path ) {
        $zip = new \ZipArchive();
        $zip->open( $file_path );
        $content = $zip->getFromName( '__meta/blacklist.txt' );
        $zip->close();

        return $content;
    }
}