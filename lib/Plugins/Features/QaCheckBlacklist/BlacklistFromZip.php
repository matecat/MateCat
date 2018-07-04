<?php

namespace Features\QaCheckBlacklist;

use ZipArchive;

class BlacklistFromZip {

    private $file_path;

    private $id_job;

    /**
     * @var \Predis\Client
     */
    private $redis ;

    public function __construct( $path, $id_job ) {
        $this->file_path = $path;
        $this->id_job    = $id_job;

        $this->redis = new \Predis\Client( \INIT::$REDIS_SERVERS );
    }

    /**
     * getMatches 
     *
     * @param $string
     *
     * @return array
     */
    public function getMatches( $string ) {
        $this->ensureCached();

        $redis           = new \Predis\Client( \INIT::$REDIS_SERVERS );
        $blacklist_rows = $redis->smembers( $this->getJobCacheKey() ) ;

        $counter = array()  ;
        
        foreach($blacklist_rows as $blacklist_item) {
            $blacklist_item = trim( $blacklist_item ) ; 
            
            if ( strlen( $blacklist_item ) == 0 ) { 
                continue ; 
            }
                
            $quoted = preg_quote( $blacklist_item );
            $matches = preg_match_all("/\\b$quoted\\b/u", $string) ;

            if ( $matches > 0 ) {
                $counter[ $blacklist_item ]  = $matches;
            }
        }

        return $counter;
    }

    /**
     * Reads the whole file and returns the content.
     *
     * @return string
     */

    public static function getContent( $zip_file_path ) {
        $zip = new ZipArchive();
        $zip->open( $zip_file_path );
        $content = $zip->getFromName( '__meta/blacklist.txt' );
        $zip->close();

        return $content;
    }


    public function ensureCached() {
        $redis   = new \Predis\Client( \INIT::$REDIS_SERVERS );
        $key     = $this->getJobCacheKey();

        if ( !$redis->exists( $key ) ) {
            $content = self::getContent( $this->file_path );
            $splitted = explode( PHP_EOL, $content );
            foreach ( $splitted as $token ) {
                $token = trim( $token );
                $redis->sadd( $key, $token );
            }
            $this->redis->expire( $key, 60 * 60 * 24 * 30 ) ;
        }
    }

    public function getCached() {
        $this->ensureCached();
    }

    private function getJobCacheKey() {
        return "blacklist:id_job:{$this->id_job}";
    }
}