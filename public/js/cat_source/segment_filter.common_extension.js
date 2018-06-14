
if ( SegmentFilter.enabled() )
(function($, UI, SF, undefined) {

    var original_renderFiles = UI.renderFiles ;
    var original_editAreaClick     = UI.editAreaClick ;

    var original_selectorForNextUntranslatedSegment = UI.selectorForNextUntranslatedSegment ; 
    var original_selectorForNextSegment = UI.selectorForNextSegment ; 
    var original_gotoNextSegment = UI.gotoNextSegment ;
    var original_gotoPreviousSegment = UI.gotoPreviousSegment ;

    var original_openNextTranslated = UI.openNextTranslated ;

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

    var gotoPreviousSegment = function() {
        var list = SegmentFilter.getLastFilterData()['segment_ids'] ;
        var index = list.indexOf( '' + UI.currentSegmentId );
        var nextFiltered = list[ index - 1 ];
        var maxReached = UI.maxNumSegmentsReached() ;

        if ( !nextFiltered ) {
            return ;
        }

        if ( maxReached ) {
            UI.unmountSegments() ;
        }

        if ( UI.Segment.findEl( nextFiltered ).length ) {
            original_gotoPreviousSegment.apply(undefined, arguments);
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
                gotoNextSegment.apply(this, arguments);
            }
            else {
                original_openNextTranslated.apply(this, arguments);
            }
        },

        gotoPreviousSegment : function() {
            if ( SF.filtering() ) {
                gotoPreviousSegment.apply(this, arguments);
            } else {
                original_gotoPreviousSegment.apply(this, arguments);
            }

        },

        gotoNextSegment : function() {
            if ( SF.filtering() ) {
                gotoNextSegment.apply(this, arguments);
            } else {
                original_gotoNextSegment.apply(this, arguments);
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

        editAreaClick : function(target, operation) {
            var e = arguments[0];
            if ( ! UI.isMuted(target) ) {
                original_editAreaClick.apply( this,[target, operation]);
            }
        },

        renderFiles : function() {
            original_renderFiles.apply( this, arguments );

            if (SF.filtering()) {
                // var segments = SegmentStore.getAllSegments();
                var filterArray = SF.getLastFilterData()['segment_ids'];
                SegmentActions.setMutedSegments(filterArray);
                // segments.forEach(function (segment,index) {
                //     if (filterArray.indexOf(segment.sid) === -1) {
                //         SegmentActions.addClassToSegment(segment.sid, 'muted');
                //         SegmentActions.removeClassToSegment(segment.sid, 'editor opened');
                //     }
                // })
            }
        }
    });
})(jQuery, UI, SegmentFilter);
