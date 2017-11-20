<?php

namespace Features ;

class ReviewImproved extends AbstractRevisionFeature {
    const FEATURE_CODE = 'review_improved' ;

    protected static $conflictingDependencies = [
            ReviewExtended::FEATURE_CODE
    ];

}
