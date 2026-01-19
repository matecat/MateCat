<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 13/08/24
 * Time: 16:37
 *
 */

namespace Model\Filters\DTO;

interface IDto
{

    public function fromArray(array $data): void;

}