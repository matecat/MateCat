<?php


namespace Features ;

class ReviewExtended extends AbstractRevisionFeature {
    const FEATURE_CODE = 'review_extended' ;

    protected static $conflictingDependencies = [
        ReviewImproved::FEATURE_CODE
    ];

}