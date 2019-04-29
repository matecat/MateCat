<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 30/03/2018
 * Time: 12:07
 */

namespace Features;


interface IBaseFeature {
    /**
     * This method returns true if the feature is to be added to project metadata `features` key.
     *
     * @return mixed
     */
    public function isAutoActivableOnProject() ;

    public function isForceableOnProject();

    /**
     * These are the dependencies we need to make to be enabled when a dependecy is
     * activated for a given project. These will fill the project metadata table.
     *
     * @return array
     */
    public static function getDependencies();

    public static function getConflictingDependencies();

}