<?php

namespace Model\Pagination;

use Model\DataAccess\DaoCacheTrait;
use PDO;
use ReflectionException;

/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 09/08/24
 * Time: 14:24
 *
 */
class Pager
{

    use DaoCacheTrait;

    protected PDO $connection;

    /**
     * @param PDO $connection
     */
    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param int                  $totals
     * @param PaginationParameters $paginationParameters
     *
     * @return array
     * @throws ReflectionException
     */
    public function getPagination(int $totals, PaginationParameters $paginationParameters): array
    {
        $this->setCacheTTL($paginationParameters->getTtl());

        $count  = $totals + 1;
        $pages  = ceil($count / $paginationParameters->getPagination());
        $prev   = ($paginationParameters->getCurrent() !== 1) ? $paginationParameters->getBaseRoute() . ($paginationParameters->getCurrent() - 1) : null;
        $next   = ($paginationParameters->getCurrent() < $pages) ? $paginationParameters->getBaseRoute() . ($paginationParameters->getCurrent() + 1) : null;
        $offset = ($paginationParameters->getCurrent() - 1) * $paginationParameters->getPagination();

        $paginationStatement = $this->connection->prepare(
                sprintf($paginationParameters->getQuery(), $paginationParameters->getPagination(), $offset)
        );

        if (!empty($paginationParameters->getCacheKeyMap())) {
            $_cacheResult = $this->_getFromCacheMap(
                    $paginationParameters->getCacheKeyMap(),
                    $paginationStatement->queryString . $this->_serializeForCacheKey($paginationParameters->getBindParams()) . $paginationParameters->getFetchClass()
            );

            if (!empty($_cacheResult)) {
                return $this->format(
                        $paginationParameters->getCurrent(),
                        $paginationParameters->getPagination(),
                        $pages,
                        $count,
                        $_cacheResult,
                        $prev,
                        $next
                );
            }
        }

        $paginationStatement->setFetchMode(PDO::FETCH_CLASS, $paginationParameters->getFetchClass());
        $paginationStatement->execute($paginationParameters->getBindParams());
        $result = $paginationStatement->fetchAll();

        if (!empty($paginationParameters->getCacheKeyMap())) {
            $this->_setInCacheMap(
                    $paginationParameters->getCacheKeyMap(),
                    $paginationStatement->queryString . $this->_serializeForCacheKey($paginationParameters->getBindParams()) . $paginationParameters->getFetchClass(),
                    $result
            );
        }

        return $this->format(
                $paginationParameters->getCurrent(),
                $paginationParameters->getPagination(),
                $pages,
                $count,
                $result,
                $prev,
                $next
        );
    }

    /**
     * @param string     $query
     * @param array|null $parameters
     *
     * @return int
     */
    public function count(string $query, ?array $parameters = []): int
    {
        $statementCount = $this->connection->prepare($query);
        $statementCount->execute($parameters);
        $count = $statementCount->fetch(PDO::FETCH_NUM);

        return $count[ 0 ] ?? 0;
    }

    /**
     * @param int         $current
     * @param int         $pagination
     * @param int         $pages
     * @param int         $total
     * @param array       $items
     *
     * @param string|null $prev
     * @param string|null $next
     *
     * @return array
     */
    protected function format(int $current, int $pagination, int $pages, int $total, array $items, ?string $prev, ?string $next): array
    {
        return [
                'current_page' => $current,
                'per_page'     => $pagination,
                'last_page'    => $pages,
                'total'        => $total,
                'prev'         => $prev,
                'next'         => $next,
                'items'        => $items,
        ];
    }

}