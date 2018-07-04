<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 01/09/2017
 * Time: 15:08
 */

namespace Features\Dqf\Service;


class MasterProjectFiles extends AbstractProjectFiles {

    protected function getFilesPath() {
        return '/project/master/%s/file' ;
    }


}