<?php

use Features\BaseFeature;
use Features\Dqf;
use Features\ReviewExtended;
use Klein\Klein;


/**
 * Class Features
 *
 * This class should be replaced by FeatureSet, which will be capable
 * to work with owner based features and environment based features.
 *
 *
 */
class Features {
    
    const PROJECT_COMPLETION   = 'project_completion';
    const TRANSLATION_VERSIONS = 'translation_versions';
    const REVIEW_IMPROVED      = 'review_improved';
    const QACHECK_GLOSSARY     = 'qa_check_glossary';
    const QACHECK_BLACKLIST    = 'qa_check_blacklist';
    const DQF                  = Dqf::FEATURE_CODE  ;
    const REVIEW_EXTENDED      = ReviewExtended::FEATURE_CODE  ;

    public static $VALID_CODES = array(
        Features::PROJECT_COMPLETION,
        Features::TRANSLATION_VERSIONS,
        Features::REVIEW_IMPROVED,
        Features::QACHECK_GLOSSARY,
        Features::QACHECK_BLACKLIST
    );

    public static $FEATURES_WITH_DEPENDENCIES = [
            self::DQF
    ];

    /**
     * Give your plugins the possibilty to install routes
     *
     * @param Klein $klein
     */
    public static function loadRoutes( Klein $klein ) {
        list( $null, $prefix, $class_name) = explode('/', $_SERVER['REQUEST_URI']);

        if ( $prefix  == 'plugins' ) {
            $cls = '\\Features\\' .  Utils::underscoreToCamelCase( $class_name );

            if ( class_exists( $cls ) ) {
                $klein->with("/$prefix/$class_name", function() use ($cls, $klein) {
                    /**
                     * @var $cls BaseFeature
                     */
                    $cls::loadRoutes( $klein );
                });
            }
        }

    }

}
