<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 25/09/18
 * Time: 11.11
 *
 */

namespace Model\Segments;

use ArrayAccess;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\ArrayAccessTrait;
use Model\DataAccess\IDaoStruct;

class ContextStruct extends AbstractDaoSilentStruct implements IDaoStruct, ArrayAccess
{

    use ArrayAccessTrait;

    public ?int $id      = null;
    public int  $id_project;
    public int  $id_segment;
    public ?int $id_file = null;
    /**
     * @var string|array
     */
    public mixed $context_json;

    public function __construct(array $array_params = [], $decode = true)
    {
        parent::__construct($array_params);
        if ($decode) {
            $this->context_json = json_decode($this->context_json);
        }
    }

}