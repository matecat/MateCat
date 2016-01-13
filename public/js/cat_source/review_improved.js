/**
 * Main file of review_improved feature.
 *
 * This file is divided in two parts: the first is for the
 * initialization of the review interface, the second for
 * initialization of translate page.
 *
 * For translate and review pages two different tabs are configured
 * for the segment footer.
 *
 * For translate page, review specific statuses are not offered.
 *
 */
ReviewImproved = window.ReviewImproved || {};

ReviewImproved.enabled = function() {
    return Review.type == 'improved';
}

/**
 * Review page
 */

if ( ReviewImproved.enabled() && config.isReview ) {
    SegmentActivator.registry.push(function( sid ) {
        var segment = UI.Segment.find( sid );
        // TODO: refactor this, the click to activate a
        // segment is not a good way to handle.
        segment.el.find('.errorTaggingArea').click();
    });

    // Register footer
    UI.SegmentFooter.registerTab({
        code                : 'review',
        tab_class           : 'review',
        label               : 'Revise',
        activation_priority : 60,
        tab_position        : 50,
        is_enabled    : function( footer ) {
            return true;
        },
        tab_markup          : function( footer ) {
            return this.label ;
        },
        content_markup      : function( footer ) {
            var data = { id : footer.segment.id };
            return MateCat.Templates['review_improved/review_tab_content'](data);
        },
        is_hidden    : function( footer ) {
            return false;
        },
    });


}

/**
 * Translate page
 */

if ( ReviewImproved.enabled() && !config.isReview ) {
(function($, root, undefined) {
    UI.SegmentFooter.registerTab({
        code                : 'review',
        tab_class           : 'review',
        label               : 'Review issues',
        activation_priority : 60,
        tab_position        : 50,
        is_enabled    : function( footer ) {
            return true;
        },
        tab_markup          : function( footer ) {
            return this.label ;
        },
        content_markup      : function( footer ) {
            var data = { id : footer.segment.id };
            return MateCat.Templates['review_improved/issues_tab_content'](data);
        },
        is_hidden    : function( footer ) {
            return false;
        },
    });

    $.extend(UI, {
        showRevisionStatuses : function() {
            return false;
        }
    });

})(jQuery, window) ;
}
