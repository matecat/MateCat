ReviewImproved = window.ReviewImproved || {};

ReviewImproved.enabled = function() {
    return Review.type == 'improved';
}


if ( ReviewImproved.enabled() && config.isReview ) {
    // Review page

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

if ( ReviewImproved.enabled() && !config.isReview ) {
(function($, root, undefined) {

    // Translate page
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
            return MateCat.Templates['review_improved/issues_tab_content'](data);
        },
        is_hidden    : function( footer ) {
            return false;
        },
    });



})(jQuery, window) ;
}
