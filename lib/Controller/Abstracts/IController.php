<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 29/08/2017
 * Time: 12:40
 */

namespace Controller\Abstracts;


use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;

interface IController {

    /**
     * @return null|\Model\Users\UserStruct
     */
    public function getUser(): ?UserStruct;

    public function isLoggedIn(): bool;

    public function getFeatureSet();

    /**
     * @param FeatureSet $featureSet
     *
     * @return mixed
     */
    public function setFeatureSet( FeatureSet $featureSet );

    public function isView(): bool;

}