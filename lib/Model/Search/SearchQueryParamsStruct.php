<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 16/04/18
 * Time: 14.09
 *
 */

namespace Search;

use DataAccess\ShapelessConcreteStruct;

class SearchQueryParamsStruct extends ShapelessConcreteStruct {

    /**
     * @var string
     */
    public $key;

    /**
     * @var integer
     */
    public $job;

    /**
     * @var $password
     */
    public $password;

    /**
     * @var string
     */
    public $searchWhere;

    /**
     * @var string
     */
    public $target;

    /**
     * @var string
     */
    public $source;

    /**
     * @var string
     */
    public $replacement;

    /**
     * @var string
     */
    public $status;

    /**
     * @var string|\stdClass
     */
    public $matchCase;

    /**
     * @var string|\stdClass
     */
    public $exactMatch;
    
}