
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
        getLastFilterData : function() {
            return this.getStoredState().serverData ;
        },

        filterPanelOpen : function() {
            return UI.body.hasClass('filtering');
        },

        /**
         * This function return true if the user is in a filtered session with zoomed segments.
         *
         * @returns {*}
         */
        filtering : function() {
            return UI.body.hasClass('sampling-enabled');
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

        restore : function( data ) {
            // debugger  // TODO, find who calls this
            window.segment_filter_panel.setState( this.getStoredState().reactState ) ;
            $(document).trigger('segment-filter-submit');
        },

        filterSubmit : function( data, wantedSegment) {
            if (!wantedSegment) {
                wantedSegment = null;
            }
            $('body').addClass('sampling-enabled');

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

                window.segment_filter_panel.setState( reactState );

                UI.clearStorage('SegmentFilter');

                SegmentFilter.setStoredState({
                    serverData : data ,
                    reactState : window.segment_filter_panel.state
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
            UI.body.addClass('filtering'); // filtering makes sense if we have serverData

            if ( this.getStoredState().serverData ) {
                SegmentActions.setMutedSegments(this.getStoredState().serverData.segment_ids);
                UI.body.addClass('sampling-enabled');

                setTimeout( function() {
                    tryToFocusLastSegment();
                }, 600 );
            }

            $(document).trigger('header-tool:open', { name: 'filter' });
        },

        clearFilter : function() {
            this.clearStoredData();
            window.segment_filter_panel.resetState();

            this.closeFilter() ;
        },

        closeFilter : function() {
            if ( UI.body.hasClass('filtering') ) {
                UI.body.removeClass('filtering');
                UI.body.removeClass('sampling-enabled');
                SegmentActions.removeAllMutedSegments();
                setTimeout( function() {
                    UI.scrollSegment( UI.currentSegment ) ;
                }, 600 );
            }
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

    $(document).on("files:appended", function() {
        // mount the hiddent react component by default so we can keep status
        window.segment_filter_panel = ReactDOM.render(
            React.createElement(
                SegmentFilter_MainPanel, {
                    isReview: config.isReview,

                }),
                $('#segment-filter-mountpoint')[0]
            );

        window.bulk_approve_bar = ReactDOM.render(
            React.createElement(
                SegmentBulkPanel_MainPanel, {
                    isReview: config.isReview
                }),
            $('#bulk-approve-bar-mountpoint')[0]
        );
        window.search_compontent = ReactDOM.render(
            React.createElement(
                Search_MainPanel, {
                    isReview: config.isReview,
                    searchable_statuses: config.searchable_statuses
                }),
            $('#search-mountpoint')[0]
        );

    });

    $(document).on('header-tool:open', function(e, data) {
        if ( data.name != 'filter' ) {
            SF.closeFilter();
        }
    });

    $(document).on('click', "header .filter", function(e) {
        e.preventDefault();

        if ( UI.body.hasClass('filtering') ) {
            SF.closeFilter();
        } else {
            UI.closeAllMenus(e);
            SF.openFilter();
        }
    });


})(jQuery, UI, SegmentFilter);
