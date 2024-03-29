<?php

class Constants {
    const SOURCE_PAGE_TRANSLATE = 1 ;
    const SOURCE_PAGE_REVISION = 2 ;
    const SOURCE_PAGE_REVISION_2 = 3 ;

    const SESSION_ACTUAL_SOURCE_LANG = 'actualSourceLang';

    const COOKIE_SOURCE_LANG = 'sourceLang';
    const COOKIE_TARGET_LANG = 'targetLang';

    const EMPTY_VAL = '_EMPTY_';

    const DEFAULT_SOURCE_LANG = 'en-US';
    const DEFAULT_TARGET_LANG = 'fr-FR';

    const OAUTH_TOKEN_KEY_FILE = '/inc/oauth-token-key.txt';

    const PUBLIC_TM  = "Public TM";
    const NO_DESCRIPTION_TM = "No description";

    public static $allowed_seg_rules = [
            'standard',
            'patent',
            'paragraph',
            ''
    ];

    /**
     * @throws Exception
     */
    public static function validateSegmentationRules( $segmentation_rule ) {

        $segmentation_rule = ( !empty( $segmentation_rule ) ) ? $segmentation_rule : '';

        if ( !in_array( $segmentation_rule, Constants::$allowed_seg_rules ) ) {
            throw new Exception( "Segmentation rule not allowed: " . $segmentation_rule, -4 );
        }

        //normalize segmentation rule to what it's used internally
        if ( $segmentation_rule == 'standard' || $segmentation_rule == '' ) {
            $segmentation_rule = null;
        }

        return $segmentation_rule;

    }
    
}
