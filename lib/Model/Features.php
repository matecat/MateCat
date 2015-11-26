<?php

class Features {
  const PROJECT_COMPLETION = 'project_completion' ;
  const TRANSLATION_VERSIONS = 'translation_versions'  ;
  const REVIEW_IMPROVED = 'review_improved' ;

  public static $VALID_CODES = array(
    Features::PROJECT_COMPLETION,
    Features::TRANSLATION_VERSIONS,
    Features::REVIEW_IMPROVED
  );

  public static function enabled($owner, $feature_code) {

  }

}
