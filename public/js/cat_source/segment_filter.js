
SegmentFilter = window.SegmentFilter || {};

SegmentFilter.enabled = function() {
    return ReviewImproved.enabled() || ReviewExtended.enabled() || ReviewExtendedFooter.enabled();
};

if (SegmentFilter.enabled())
(function($, UI, SF, undefined) {

    var cachedStoredState = null ;

    var keyForLocalStorage = function() {
        var page = ( config.isReview ? 'revise' : 'translate' );
        return 'SegmentFilter-v2-' + page + '-' + config.id_job + '-' + config.password ;
    } ;

    var segmentIsInSample = function( segmentId, listOfSegments ) {
        return listOfSegments.indexOf( segmentId ) !== -1 ;
    }

    var notification;

    var callbackForSegmentNotInSample = function( segmentId ) {
        var title = 'Segment not in sample' ;
        var text = 'Sample is trying to focus on segment #' + segmentId + ', but ' +
                'segment is no longer in the sample' ;

        return function() {
            if ( notification ) APP.removeNotification( notification ) ;

            notification = APP.addNotification({
                autoDismiss : false,
                dismissable : true,
                position    : "bl",
                text        : text,
                title       : title,
                type        : 'warning',
                allowHtml   : true
            });
        } ;

    } ;

    $.extend(SF, {
        open: false,
        getLastFilterData : function() {
            return this.getStoredState().serverData ;
        },

        /**
         * This function return true if the user is in a filtered session with zoomed segments.
         *
         * @returns {*}
         */
        filtering : function() {
            return this.open;
        },

        /**
         * @returns {{reactState: null, serverData: null, lastSegmentId: null}}
         */
        getStoredState : function() {
            if ( null != cachedStoredState ) {
                return cachedStoredState ;
            }

            var data = localStorage.getItem( keyForLocalStorage() ) ;

            if ( data ) {
                try {
                    cachedStoredState = JSON.parse( data ) ;
                }
                catch( e ) {
                    this.clearStoredData();
                    console.error( e.message );
                }
            }
            else {
                cachedStoredState = {
                    reactState: null,
                    serverData : null,
                    lastSegmentId : null
                }  ;
            }

            return cachedStoredState ;
        },

        setStoredState : function( data ) {
           cachedStoredState = $.extend( this.getStoredState(), data );
           localStorage.setItem(keyForLocalStorage(), JSON.stringify( cachedStoredState ) ) ;
        },

        clearStoredData : function() {
            cachedStoredState = null ;
            return localStorage.removeItem( keyForLocalStorage() ) ;
        },

        filterSubmit : function( data, wantedSegment) {
            if (!wantedSegment) {
                wantedSegment = null;
            }
            this.open = true;
            data = { filter: data } ;

            var path = sprintf('/api/v2/jobs/%s/%s/segments-filter?%s',
                              config.id_job, config.password, $.param( data ) );

            return $.getJSON(path).pipe(function( data ) {
                $(document).trigger('segment-filter:filter-data:load', { data: data });

                var reactState = {
                    filteredCount : data.count,
                    filtering : true,
                    segmentsArray: data.segment_ids
                } ;
                CatToolActions.setSegmentFilter(data);

                UI.clearStorage('SegmentFilter');

                SegmentFilter.setStoredState({
                    serverData : data ,
                    reactState : reactState
                }) ;

                //UI.unmountSegments();
                SegmentActions.setMutedSegments(data[ 'segment_ids' ]);

                var segmentToOpen ;

                if ( !wantedSegment ) {
                    segmentToOpen =  data[ 'segment_ids' ] [ 0 ] ;
                    var segment$ = UI.getSegmentById(segmentToOpen);
                    if (segment$.length) {
                        UI.openSegment(segment$)
                    } else {
                        UI.scrollSegment(segment$, segmentToOpen);
                    }
                } else if ( wantedSegment && !segmentIsInSample( wantedSegment, data[ 'segment_ids' ] ) ) {
                    segmentToOpen =  data[ 'segment_ids' ] [ 0 ] ;
                    callbackForSegmentNotInSample( wantedSegment )  ;
                } else {
                    segmentToOpen = wantedSegment ;
                    var segment$ = UI.getSegmentById(segmentToOpen);
                    if (segment$) {
                        UI.openSegment(segment$)
                    } else {
                        UI.scrollSegment(segment$, segmentToOpen);
                    }
                }

            })
        },

        /**
         * This function gets called when segments are still to be rendered
         * and sometimes when the segments are rendered ( click on filter icon ).
         *
         *
         */
        openFilter : function() {

            CatToolActions.openSegmentFilter();
            if ( this.getStoredState().serverData ) {
                SegmentActions.setMutedSegments(this.getStoredState().serverData.segment_ids);
                this.open = true;
                setTimeout( function() {
                    tryToFocusLastSegment();
                }, 600 );
            }

        },

        clearFilter : function() {
            this.clearStoredData();
            this.closeFilter() ;
        },

        closeFilter : function() {
            CatToolActions.closeSubHeader();
            this.open = false;
            SegmentActions.removeAllMutedSegments();
            setTimeout( function() {
                UI.scrollSegment( UI.currentSegment ) ;
            }, 600 );
        }
    });

    $(document).on('segmentsAdded', function(e) {
        if ( SegmentFilter.filtering() ) {
            tryToFocusLastSegment();
        }
    });

    function tryToFocusLastSegment() {
        var segment = UI.Segment.find( SegmentFilter.getStoredState().lastSegmentId ) ;

        if ( ! (SegmentFilter.getStoredState().lastSegmentId && segment ) ) {
            return ; // the stored lastSegmentId is not in the DOM, this should never happen
        }

        if ( segment.el.is( UI.currentSegment ) ) {
            if ( UI.body.hasClass( 'editing' ) ) {
                UI.scrollSegment( segment.el ) ;
            }
            else {
                segment.el.find( UI.targetContainerSelector() ).click();
            }
        }
        else {
            segment.el.find( UI.targetContainerSelector() ).click();
        }
    }

    $(document).on('segment:activate', function( event, data ) {
        if ( SegmentFilter.filtering() ) {
            SegmentFilter.setStoredState({ lastSegmentId : data.segment.absId }) ;
        }
    });

    $(document).on('click', "header .filter", function(e) {
        e.preventDefault();
        CatToolActions.toggleSegmentFilter();
        SegmentFilter.openFilter();
    });


})(jQuery, UI, SegmentFilter);
