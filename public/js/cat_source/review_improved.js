ReviewImproved = window.ReviewImproved || {};

ReviewImproved.enabled = function() {
    return Review.type == 'improved';
}


if ( ReviewImproved.enabled() && config.isReview ) {
    SegmentActivator.registry.push(function( sid ) {
        var segment = UI.Segment.find( sid );
        // TODO: refactor this, the click to activate a
        // segment is not a good way to handle.
        segment.el.find('.errorTaggingArea').click();
    });
}

if ( ReviewImproved.enabled() && !config.isReview ) {
(function($, root, undefined) {

    function activateTab( segment ) {
        $('.editor .submenu .active').removeClass('active');
        $('.tab-switcher-issues').addClass('active');

        $('.editor .sub-editor.open').removeClass('open');
        $('.editor .sub-editor.segment-issues').addClass('open');
    }

    $(document).on('click', '.tab-switcher-issues', function(e) {
        var segment = new UI.Segment( $( e.target ).closest( 'section' ) );
        e.preventDefault();
        activateTab( segment );
    });

    $.extend(ReviewImproved, {
        translateFooterTabHTML : function( sid ) {
            return template('review_improved/issues_tab', {
                id_segment : sid
            })[0].outerHTML ;
        },
        translateFooterTabContentHTML : function( sid ) {
            return template('review_improved/issues_tab_content', {
                id_segment: sid
            })[0].outerHTML ;
        }
    });
})(jQuery, window) ;
}
