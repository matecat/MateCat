<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 29/08/2017
 * Time: 12:40
 */

namespace Controller\Abstracts;


use FeatureSet;
use Users_UserStruct;

interface IController {

    /**
     * @return null|Users_UserStruct
     */
    public function getUser(): ?Users_UserStruct;

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