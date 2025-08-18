<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 09/08/24
 * Time: 15:50
 *
 */

namespace Model\Pagination;

class PaginationParameters {

    protected string  $fetchClass;
    protected int     $current;
    protected int     $pagination;
    protected string  $baseRoute;
    protected ?string $cacheKeyMap;
    protected ?int    $ttl;
    protected array   $bindParams;
    protected string  $query;

    /**
     * @param string   $query
     * @param array    $bindParams
     * @param string   $fetchClass
     * @param string   $baseRoute
     * @param int|null $current
     * @param int|null $pagination
     */
    public function __construct( string $query, array $bindParams, string $fetchClass, string $baseRoute, ?int $current = 1, ?int $pagination = 20 ) {
        $this->query      = $query;
        $this->bindParams = $bindParams;
        $this->fetchClass = $fetchClass;
        $this->current    = $current;
        $this->pagination = $pagination;
        $this->baseRoute  = $baseRoute;
    }

    public function setCache( string $cacheKeyMap, ?int $ttl = 60 * 60 * 24 ) {
        $this->cacheKeyMap = $cacheKeyMap;
        $this->ttl         = $ttl;
    }

    public function getQuery(): string {
        return $this->query;
    }

    public function getBindParams(): array {
        return $this->bindParams;
    }

    public function getFetchClass(): string {
        return $this->fetchClass;
    }

    public function getCurrent(): int {
        return $this->current;
    }

    public function getPagination(): int {
        return $this->pagination;
    }

    public function getBaseRoute(): string {
        return $this->baseRoute;
    }

    public function getCacheKeyMap(): ?string {
        return $this->cacheKeyMap;
    }

    public function getTtl(): ?int {
        return $this->ttl;
    }

}