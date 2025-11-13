<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 16/04/18
 * Time: 14.09
 *
 */

namespace Model\Search;

use Model\DataAccess\ShapelessConcreteStruct;
use stdClass;

class SearchQueryParamsStruct extends ShapelessConcreteStruct
{

    /**
     * @var string|null
     */
    public ?string $key = null;

    /**
     * @var integer
     */
    public int $job;

    /**
     * @var string $password
     */
    public string $password;

    /**
     * @var string|null
     */
    public ?string $target = null;

    /**
     * @var string|null
     */
    public ?string $source = null;

    /**
     * @var ?string
     */
    public ?string $replacement = null;

    /**
     * @var ?string
     */
    public ?string $status = null;

    /**
     * @var bool
     */
    public bool $isMatchCaseRequested;

    /**
     * @var bool
     */
    public bool $isExactMatchRequested;


    /**
     * @var ?stdClass
     */
    public ?stdClass $matchCase = null;

    /**
     * @var ?stdClass
     */
    public ?stdClass $exactMatch = null;

}