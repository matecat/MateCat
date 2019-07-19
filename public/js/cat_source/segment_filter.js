
SegmentFilter = window.SegmentFilter || {};

SegmentFilter.enabled = function() {
    return config.segmentFilterEnabled;
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
    };

    var notification;

    var callbackForSegmentNotInSample = function( segmentId ) {
        var title = 'Segment not in sample' ;
        var text = 'Sample is trying to focus on segment #' + segmentId + ', but ' +
                'segment is no longer in the sample' ;

        return (function() {
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
        })() ;

    } ;

    $.extend(SF, {
        open: false,
        filteringSegments: false,
        getLastFilterData : function() {
            return this.getStoredState().serverData ;
        },

        /**
         * This function return true if the user is in a filtered session with zoomed segments.
         *
         * @returns {*}
         */
        filtering : function() {
            return this.filteringSegments;
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

        filterSubmit : function( params ,extendendLocalStorageValues) {
            if(!extendendLocalStorageValues){
                extendendLocalStorageValues = {};
            }
            this.filteringSegments = true;
            var data = { filter: params } ;
            data.revision_number = params.revision_number;
            data.filter.revision = config.isReview;
            var password = (config.isReview) ? config.review_password : config.password;
            var path = sprintf('/api/v2/jobs/%s/%s/segments-filter?%s',
                              config.id_job, password, $.param( data ) );

            return $.getJSON(path).pipe(function( data ) {
                UI.clearStorage('SegmentFilter');

                SegmentActions.removeAllMutedSegments();

                $(document).trigger('segment-filter:filter-data:load', { data: data });

                var reactState = Object.assign({
                    filteredCount : data.count,
                    filtering : true,
                    segmentsArray: data.segment_ids
                },extendendLocalStorageValues);

                SegmentFilter.setStoredState({
                    serverData : data ,
                    reactState : reactState
                }) ;

                CatToolActions.setSegmentFilter(data);

                SegmentActions.setMutedSegments(data[ 'segment_ids' ]);

                var segmentToOpen ;
                var lastSegmentId = SegmentFilter.getStoredState().lastSegmentId;
                if ( !lastSegmentId ) {
                    segmentToOpen =  data[ 'segment_ids' ] [ 0 ] ;
                    SegmentActions.openSegment(segmentToOpen);
                } else if ( lastSegmentId && !segmentIsInSample( lastSegmentId, data[ 'segment_ids' ] ) ) {
                    callbackForSegmentNotInSample( lastSegmentId )  ;
                } else {
                    segmentToOpen = lastSegmentId ;
                    SegmentActions.openSegment(segmentToOpen);
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
            this.open = true;
            var localStorageData = this.getStoredState();
            if ( localStorageData.serverData ) {
                SegmentActions.setMutedSegments(this.getStoredState().serverData.segment_ids);
                CatToolActions.setSegmentFilter(localStorageData.serverData, localStorageData.reactState);
                this.filteringSegments = true;
                setTimeout( function() {
                    tryToFocusLastSegment();
                }, 200 );
            }

        },

        clearFilter : function() {
            this.clearStoredData();
            this.filteringSegments = false;
            SegmentActions.removeAllMutedSegments();
        },

        closeFilter : function() {
            CatToolActions.closeSubHeader();
            this.open = false;
            SegmentActions.removeAllMutedSegments();
            setTimeout( function() {
                UI.scrollSegment( UI.currentSegmentId ) ;
            }, 600 );
        },
        goToNextRepetition: function ( button, status ) {
            var hash = UI.currentSegment.data('hash');
            var segmentFilterData = SegmentFilter.getStoredState();
            var groupArray = segmentFilterData.serverData.grouping[hash];
            var index = groupArray.indexOf(UI.currentSegmentId);
            var nextItem;
            if(index >= 0 && index < groupArray.length - 1) {
                nextItem = groupArray[index + 1]
            } else {
                nextItem = groupArray[0];
            }
            UI.changeStatus(button, status, 0, function(){SegmentActions.openSegment(nextItem);});

            UI.setStatusButtons(UI.currentSegment.find('a.translated'));


        },
        goToNextRepetitionGroup: function ( button, status ) {
            var hash = UI.currentSegment.data('hash');
            var segmentFilterData = SegmentFilter.getStoredState();
            var groupsArray = Object.keys(segmentFilterData.serverData.grouping);
            var index = groupsArray.indexOf(hash);
            var nextGroupHash;
            if(index >= 0 && index < groupsArray.length - 1) {
                nextGroupHash = groupsArray[index + 1]
            } else {
                nextGroupHash = groupsArray[0];
            }
            var nextItem = segmentFilterData.serverData.grouping[nextGroupHash][0];

            UI.changeStatus(button, status, 0, function(){SegmentActions.openSegment(nextItem);});

            UI.setStatusButtons(UI.currentSegment.find('a.translated'));
        }

    });

    $(document).on('segmentsAdded', function(e) {
        if ( SegmentFilter.filtering() ) {
            tryToFocusLastSegment();
        }
    });

    function tryToFocusLastSegment() {
        var segment = SegmentStore.getSegmentByIdToJS( SegmentFilter.getStoredState().lastSegmentId ) ;

        if ( ! (SegmentFilter.getStoredState().lastSegmentId && segment ) ) {
            return ; // the stored lastSegmentId is not in the DOM, this should never happen
        }

        if ( segment.opened ) {
            UI.scrollSegment( segment.original_sid ) ;
        } else {
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
        if (!SegmentFilter.open) {
            SegmentFilter.openFilter();
        } else {
            SegmentFilter.closeFilter();
            SegmentFilter.open = false;
        }
    });


})(jQuery, UI, SegmentFilter);
