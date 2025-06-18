<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 27/04/17
 * Time: 14.50
 *
 */

namespace Comments;


class OpenThreadsStruct extends \DataAccess\AbstractDaoSilentStruct implements \DataAccess\IDaoStruct {

    public int $id_project;
    public string $password;
    public int $id_job;
    public int $count;

}