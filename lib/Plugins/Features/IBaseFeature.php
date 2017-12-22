<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 10/11/2017
 * Time: 12:25
 */

namespace Features;


interface IBaseFeature {

    /**
     * These are the dependencies we need to make to be enabled when a dependecy is
     * activated for a given project. These will fill the project metadata table.
     *
     * @return array
     */
    public static function getDependencies();

    public static function getConflictingDependencies();

}