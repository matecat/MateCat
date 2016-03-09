
SegmentFilter = window.SegmentFilter || {};

SegmentFilter.enabled = function() {
    return true;
}

if (SegmentFilter.enabled())
(function($, UI, undefined) {
    var parentGetSegmentsMarkup = UI.getSegmentMarkup ;
    var parentOpenSegment       = UI.openSegment ;
    var parentEditAreaClick     = UI.editAreaClick ;

    $.extend(UI, {

        isMuted : function(el) {
            return  $(el).closest('section').hasClass('muted');
        },

        editAreaClick : function(e, operation, action) {
            var e = arguments[0];
            if ( ! UI.isMuted(e.target) ) {
                parentEditAreaClick.apply( e.target, arguments );
            }
        },

        getSegmentMarkup : function() {
            var markup = parentGetSegmentsMarkup.apply( undefined, arguments );
            var segment = arguments[0];
            if ( parseInt( segment.sid ) % 2 == 1 ) {
                markup = $(markup).addClass('muted');
                markup = $('<div/>').append(markup).html();
            }

            return markup ;
        }
    });



})(jQuery, UI);
