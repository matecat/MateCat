<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 29/08/2017
 * Time: 12:40
 */

namespace Controller\Abstracts;


use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;

interface IController
{

    /**
     * @return null|UserStruct
     */
    public function getUser(): ?UserStruct;

    public function isLoggedIn(): bool;

    public function getFeatureSet(): FeatureSet;

    /**
     * @param FeatureSet $featureSet
     *
     * @return KleinController
     */
    public function setFeatureSet(FeatureSet $featureSet): KleinController;

    public function isView(): bool;

    public function getDatabase(): IDatabase;

}