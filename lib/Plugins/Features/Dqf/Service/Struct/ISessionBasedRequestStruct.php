<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 06/03/2017
 * Time: 12:05
 */

namespace Features\Dqf\Service\Struct;


/**
 * Interface ISessionBasedRequestStruct
 * @package Features\Dqf\Service\Struct
 *
 * @deprecated this is no longer required and should be removed
 */
interface ISessionBasedRequestStruct {

    public function getParams();
    public function getHeaders();

}
