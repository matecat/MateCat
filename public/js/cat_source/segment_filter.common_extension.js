
if ( SegmentFilter.enabled() )
(function($, UI, SF, undefined) {

    var original_getSegmentsMarkup = UI.getSegmentMarkup ;
    var original_editAreaClick     = UI.editAreaClick ;

    var original_selectorForNextUntranslatedSegment = UI.selectorForNextUntranslatedSegment ; 
    var original_selectorForNextSegment = UI.selectorForNextSegment ; 

    $.extend(UI, {
        selectorForNextUntranslatedSegment : function(status, section) {
            if ( !SF.filtering() ) {
                return original_selectorForNextUntranslatedSegment(status, section); 
            } else {
                return 'section:not(.muted)';
            }
        },

        selectorForNextSegment : function() {
            if ( !SF.filtering() ) {
                return original_selectorForNextSegment(); 
            } else  {
                return 'section:not(.muted)'; 
            }
        },

        isMuted : function(el) {
            return  $(el).closest('section').hasClass('muted');
        },

        editAreaClick : function(e, operation, action) {
            var e = arguments[0];
            if ( ! UI.isMuted(e.target) ) {
                original_editAreaClick.apply( e.target, arguments );
            }
        },

        getSegmentMarkup : function() {
            var markup = original_getSegmentsMarkup.apply( undefined, arguments );
            var segment = arguments[0];

            if (SF.filtering()) {
                if ( SF.lastFilterData['segment_ids'].indexOf( segment.sid ) === -1 ) {
                    markup = $(markup).addClass('muted');
                    markup = $('<div/>').append(markup).html();
                }
            }

            return markup ;
        }
    });
})(jQuery, UI, SegmentFilter);
