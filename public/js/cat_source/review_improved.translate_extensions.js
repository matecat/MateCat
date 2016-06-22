if ( ReviewImproved.enabled() && !config.isReview)
(function($, root, undefined) {

    var unmountReactButtons = function( segment_el ) {
        console.log( 'unmountReactButtons', segment_el );
        var mountpoint = segment_el.find('[data-mount="main-buttons"]')[0];
        ReactDOM.unmountComponentAtNode( mountpoint );
    };

    var original_createButtons = UI.createButtons ;

    $.extend(UI, {
        showRevisionStatuses : function() {
            return false;
        },
        cleanupLegacyButtons : function( segment ) {
            var segObj ;

            if ( segment instanceof UI.Segment ) {
                segObj = segment ;
            } else {
                segObj = new UI.Segment(segment);
            }
            
            var buttonsOb = $('#segment-' + segObj.id + '-buttons');
            buttonsOb.empty();
            $('p.warnings', segObj.el).empty();
        },

        removeButtons : function(byButton, segment) {
            unmountReactButtons( segment );
            UI.cleanupLegacyButtons( segment );
        },
        /**
         * Here we create new buttons via react components
         * alongside the legacy buttons hadled with jquery.
         */
        createButtons: function(segment) {
            if ( typeof segment == 'undefined' ) {
                segment  = new UI.Segment( UI.currentSegment );
            }

            var data = MateCat.db.segments.by('sid', segment.absId );

            if ( showFixedAndRebuttedButtons( data.status ) ) {
                var mountpoint = segment.el.find('[data-mount="main-buttons"]')[0];

                ReactDOM.render( React.createElement( MC.SegmentMainButtons, {
                    status: data.status,
                    sid : data.sid
                } ), mountpoint );

            } else {
                unmountReactButtons( segment.el );
                UI.cleanupLegacyButtons( segment.el );
                original_createButtons.apply(this, segment) ;
            }
        }
    })

    var showFixedAndRebuttedButtons = function ( status ) {
        status = status.toLowerCase();
        return status == 'rejected' || status == 'fixed' || status == 'rebutted' ;
    }

})(jQuery, window);
