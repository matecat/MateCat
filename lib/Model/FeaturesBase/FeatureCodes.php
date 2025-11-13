<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 03/07/25
 * Time: 18:19
 *
 */

namespace Model\FeaturesBase;

use Plugins\Features\Mmt;
use Plugins\Features\ProjectCompletion;
use Plugins\Features\ReviewExtended;
use Plugins\Features\SecondPassReview;
use Plugins\Features\TranslationVersions;

class FeatureCodes
{

    const string PROJECT_COMPLETION   = ProjectCompletion::FEATURE_CODE;
    const string TRANSLATION_VERSIONS = TranslationVersions::FEATURE_CODE;
    const string REVIEW_EXTENDED      = ReviewExtended::FEATURE_CODE;
    const string MMT                  = Mmt::FEATURE_CODE;
    const string SECOND_PASS_REVIEW   = SecondPassReview::FEATURE_CODE;

}