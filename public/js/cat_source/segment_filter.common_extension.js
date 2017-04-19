
if ( SegmentFilter.enabled() )
(function($, UI, SF, undefined) {

    var original_getSegmentsMarkup = UI.getSegmentMarkup ;
    var original_editAreaClick     = UI.editAreaClick ;

    var original_selectorForNextUntranslatedSegment = UI.selectorForNextUntranslatedSegment ; 
    var original_selectorForNextSegment = UI.selectorForNextSegment ; 
    var original_gotoNextSegment = UI.gotoNextSegment ;
    var original_gotoPreviousSegment = UI.gotoPreviousSegment ;

    var original_openNextTranslated = UI.openNextTranslated ;

    var gotoPreviousSegment = function() {
        var list = SegmentFilter.getLastFilterData()['segment_ids'] ;
        var index = list.indexOf('' + UI.currentSegmentId);
        var nextFiltered = list[ index - 1 ];

        if ( nextFiltered && UI.Segment.findEl( nextFiltered ).length ) {
            original_gotoPreviousSegment.apply(undefined, arguments);
        } else if ( nextFiltered ) {
            UI.render({ segmentToOpen: nextFiltered });
        } else {
            // in this case there is no previous, do nothing, remain on the current segment.
        }
    };

    /**
     * This function handles the movement to the next segment when filter is open. This is a natural operation
     * that is commonly used and likely to cause the number of segments loaded to be come huge.
     *
     * To handle this case, it checks for the maxNumSegmentsReached function. If the number of segments is too high
     * it removes the DOM elements UI.unmountSegments().
     */

    var gotoNextSegment = function() {
        var list = SegmentFilter.getLastFilterData()['segment_ids'] ;
        var index = list.indexOf( '' + UI.currentSegmentId );
        var nextFiltered = list[ index + 1 ];
        var maxReached = UI.maxNumSegmentsReached() ;

        if ( !nextFiltered ) {
            return ;
        }

        if ( maxReached ) {
            UI.unmountSegments() ;
        }

        if ( UI.Segment.findEl( nextFiltered ).length ) {
            original_gotoNextSegment.apply(undefined, arguments);
        } else if ( nextFiltered ) {
            UI.render({ segmentToOpen: nextFiltered });
        }
    };

    $.extend(UI, {
        openNextTranslated : function() {
            // this is expected behaviour in review
            // change this if we are filtering, go to the next
            // segment, assuming the sample is what we want to revise.
            if ( SF.filtering() ) {
                gotoNextSegment.apply(undefined, arguments);
            }
            else {
                original_openNextTranslated.apply(undefined, arguments);
            }
        },
        gotoPreviousSegment : function() {
            if ( SF.filtering() ) {
                gotoPreviousSegment.apply(undefined, arguments);
            } else {
                original_gotoPreviousSegment.apply(undefined, arguments);
            }

        },

        gotoNextSegment : function() {
            if ( SF.filtering() ) {
                gotoNextSegment.apply(undefined, arguments);
            } else {
                original_gotoNextSegment.apply(undefined, arguments);
            }

        },
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
                original_editAreaClick.apply( $(e.target).closest('.editarea'), arguments );
            }
        },

        getSegmentMarkup : function() {
            var markup = original_getSegmentsMarkup.apply( undefined, arguments );
            var segment = arguments[0];

            if (SF.filtering()) {
                if ( SF.getLastFilterData()['segment_ids'].indexOf( segment.sid ) === -1 ) {
                    markup = $(markup).addClass('muted');
                    markup = $('<div/>').append(markup).html();
                }
            }

            return markup ;
        }
    });
})(jQuery, UI, SegmentFilter);
