<?php

namespace Utils\Constants;

use Exception;

class Constants
{

    const string SESSION_ACTUAL_SOURCE_LANG = 'actualSourceLang';

    const string COOKIE_SOURCE_LANG = 'sourceLang';
    const string COOKIE_TARGET_LANG = 'targetLang';

    const string EMPTY_VAL = '_EMPTY_';

    const string DEFAULT_SOURCE_LANG = 'en-US';
    const string DEFAULT_TARGET_LANG = 'fr-FR';

    const string OAUTH_TOKEN_KEY_FILE = '/inc/oauth-token-key.txt';

    const string PUBLIC_TM = "Public TM";
    const string NO_DESCRIPTION_TM = "No description";

    public static array $allowed_seg_rules = [
        'standard',
        'patent',
        'paragraph',
        ''
    ];

    /**
     * @throws Exception
     */
    public static function validateSegmentationRules(?string $segmentation_rule = ''): ?string
    {
        //normalize segmentation rule to what it's used internally
        if ($segmentation_rule == 'standard' || $segmentation_rule == '') {
            return null;
        }

        if (!in_array($segmentation_rule, Constants::$allowed_seg_rules)) {
            throw new Exception("Segmentation rule not allowed: " . $segmentation_rule, -4);
        }

        return $segmentation_rule;
    }

}
