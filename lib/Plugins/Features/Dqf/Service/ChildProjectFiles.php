<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 01/09/2017
 * Time: 15:10
 */

namespace Features\Dqf\Service;


class ChildProjectFiles extends AbstractProjectFiles {

    protected function getFilesPath() {
        return '/project/child/%s/file';
    }

}