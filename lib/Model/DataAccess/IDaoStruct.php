<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 29/09/14
 * Time: 17.55
 */

namespace Model\DataAccess;

/**
 * Interface IDaoStruct A generic interface that will be used by any DataAccess\AbstractDao extended object
 * @see AbstractDao
 */
interface IDaoStruct {

    public function getArrayCopy();

    public function count();

    public function toArray( array $mask = null ): array;

}