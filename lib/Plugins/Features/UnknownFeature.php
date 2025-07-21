<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 18/07/25
 * Time: 15:56
 *
 */

namespace Plugins\Features;

/**
 * Class UnknownFeature
 * This class is used when a feature is not recognized by the system.
 * It extends the BaseFeature class and does not implement any specific functionality.
 * It serves as a placeholder for features that are not defined or recognized or removed.
 * It can be used to handle cases where a feature is expected but not found,
 * allowing the system to continue functioning without crashing.
 */
class UnknownFeature extends BaseFeature {

    const FEATURE_CODE = 'UnknownFeature';
    protected bool $autoActivateOnProject = false;

}