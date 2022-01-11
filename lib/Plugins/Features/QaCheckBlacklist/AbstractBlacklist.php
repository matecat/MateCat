<?php

namespace Features\QaCheckBlacklist;

use Matecat\Finder\WholeTextFinder;
use RedisHandler;

abstract class AbstractBlacklist {

    /**
     * @var string
     */
    protected $file_path;

    /**
     * @var string
     */
    protected $id_job;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var \Predis\Client
     */
    protected $redis ;

    /**
     * AbstractBlacklist constructor.
     *
     * @param $path
     * @param $id_job
     * @param $password
     *
     * @throws \Predis\Connection\ConnectionException
     * @throws \ReflectionException
     */
    public function __construct( $path, $id_job, $password ) {
        $this->file_path = $path;
        $this->id_job    = $id_job;
        $this->password    = $password;
        $this->redis     = ( new RedisHandler() )->getConnection();
    }

    abstract public function getContent();

    /**
     * Ensure cache in Redis
     */
    private function ensureCached() {
        $key = $this->getJobCacheKey();

        if ( $this->checkIfBlacklistKeywordsExistsInCache() ) {
            $content = $this->getContent();

            $splitted = explode( PHP_EOL, $content );
            foreach ( $splitted as $token ) {
                $token = trim( $token );
                $this->redis->sadd( $key, $token );
            }

            $this->redis->expire( $key, 60 * 60 * 24 * 30 ) ;
        }
    }

    /**
     * @return bool
     */
    private function checkIfBlacklistKeywordsExistsInCache()
    {
        $key = $this->getJobCacheKey();
        $blacklistInRedis = $this->redis->smembers( $key );

        return (
            !$this->redis->exists( $key )
            or empty( $blacklistInRedis )
            or ( count($blacklistInRedis) === 1 and $blacklistInRedis[0] == "" )
        );
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

        $blacklist_rows = $this->redis->smembers( $this->getJobCacheKey() ) ;
        $counter = [];

        foreach($blacklist_rows as $blacklist_item) {
            $blacklist_item = trim( $blacklist_item ) ; 
            
            if ( strlen( $blacklist_item ) == 0 ) { 
                continue ; 
            }

            $matches = WholeTextFinder::find($string, $blacklist_item, true, true);
            $positions = [];

            if ( ! empty($matches) ) {

                foreach ($matches as $match){
                    $positions[] = [
                        'start' => $match[1],
                        'end' => $match[1] + mb_strlen($match[0]),
                    ];
                }

                $counter[] = [
                    'match' => $blacklist_item,
                    'count' => count($matches),
                    'positions' => $positions,
                ];
            }
        }

        return $counter;
    }

    /**
     * @return string
     */
    private function getJobCacheKey()
    {
        return md5("blacklist:id_job:{$this->id_job}:password:{$this->password}");
    }
}