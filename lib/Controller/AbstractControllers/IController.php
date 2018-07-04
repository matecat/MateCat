<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 29/08/2017
 * Time: 12:40
 */

namespace AbstractControllers;


use FeatureSet;

interface IController {

    public function getUser();

    public function getFeatureSet();

    /**
     * @param FeatureSet $features
     *
     * @return mixed
     */
    public function setFeatureSet( FeatureSet $features );

}