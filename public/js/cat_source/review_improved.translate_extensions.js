if ( ReviewImproved.enabled() && !config.isReview)
(function($, root, undefined) {

    var unmountReactButtons = function( segment_el ) {
        console.log( 'unmountReactButtons', segment_el );
        var mountpoint = segment_el.find('[data-mount="main-buttons"]')[0];
        ReactDOM.unmountComponentAtNode( mountpoint );
    };

    var original_createButtons = UI.createButtons ;

    var originalBindShortcuts = UI.bindShortcuts ;

    var clickOnRebutted = function(sid) {
        var el = UI.Segment.findEl(sid);
        el.removeClass('modified');
        UI.changeStatus(el, 'rebutted', true);
        UI.gotoNextSegment();
    };

    var clickOnFixed = function(sid) {
        var el = UI.Segment.findEl( sid );
        if ( el.find('.button-fixed').attr('disabled') == 'disabled' ) {
            return ;
        }

        el.removeClass('modified');
        el.data('modified',  false);
        UI.changeStatus(el, 'fixed', true);
        UI.gotoNextSegment(); // NOT ideal behaviour, would be better to have a callback chain of sort.

    };
    var handleKeyPressOnMainButton = function(e) {
        if ( $('.editor .buttons .button-rebutted').length ) {
            clickOnRebutted(UI.currentSegmentId);
        }
        else if ( $('.editor .buttons .button-fixed').length ) {
            clickOnFixed(UI.currentSegmentId);
        }
    }

    $.extend(ReviewImproved, {
        clickOnRebutted : clickOnRebutted,
        clickOnFixed : clickOnFixed,
    });

    $.extend(UI, {

        bindShortcuts: function() {
            originalBindShortcuts();
            $("body").on('keydown.shortcuts', null, UI.shortcuts.translate.keystrokes.standard, handleKeyPressOnMainButton );
            $("body").on('keydown.shortcuts', null, UI.shortcuts.translate.keystrokes.mac, handleKeyPressOnMainButton );
        },

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
