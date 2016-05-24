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
     *
     *
     * @param $string
     *
     * @return array
     */
    public function getMatches( $string ) {
        $this->ensureCached();

        $key  = "blacklist:trg:" . md5( $string ) ;

        $splitted_string = explode( " ", $string );
        $redis           = new \Predis\Client( \INIT::$REDIS_SERVERS );

        foreach ( $splitted_string as $word ) {
            $word  = trim( $word );
            $this->redis->sadd( $key, $word );
        }

        $this->redis->expire( $key, 60 * 60 * 24 * 30 ) ;

        $results = $redis->sinter(array( $this->getJobCacheKey(), $key ));

        $results = array_filter( $results, function( $item ) {
            $item = trim($item) ;
            return strlen( $item ) > 0 ;
        });

        $results = array_values( $results );

        $counter = array()  ;

        foreach( $results as $result ) {
            $quoted = preg_quote( $result );
            $matches = preg_match_all("/\\b$quoted\\b/", $string) ;

            $counter[ $result ] = $matches ;
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