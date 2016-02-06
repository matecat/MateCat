if ( ReviewImproved.enabled() && !config.isReview)
(function($, root, undefined) {

    var unmountReactButtons = function( segment_el ) {
        console.log( 'unmountReactButtons', segment_el );
        var mountpoint = segment_el.find('[data-mount="main-buttons"]')[0];
        ReactDOM.unmountComponentAtNode( mountpoint );
    };

    $.extend(UI, {
        cleanupLegacyButtons : function( segment ) {
            var buttonsOb = $('#segment-' + segment.id + '-buttons');
            buttonsOb.empty();
        },

        createLegacyButtons : function( segment ) {
            var seg_el = segment.el ;

            var button_label = config.status_labels.TRANSLATED ;
            var label_first_letter = button_label[0];

            var disabled = (seg_el.hasClass('loaded')) ? '' : ' disabled="disabled"';
            var nextSegment = segment.el.next();
            var sameButton = (nextSegment.hasClass('status-new')) || (nextSegment.hasClass('status-draft'));
            var nextUntranslated = (sameButton)? '' : '<li><a id="segment-' + segment.id +
                '-nextuntranslated" href="#" class="btn next-untranslated" data-segmentid="segment-' +
                segment.id + '" title="Translate and go to next untranslated">' +
                label_first_letter + '+&gt;&gt;</a><p>' +

            ((UI.isMac) ? 'CMD' : 'CTRL') + '+SHIFT+ENTER</p></li>';

            UI.segmentButtons = nextUntranslated + '<li><a id="segment-' + segment.id +
                '-button-translated" data-segmentid="segment-' + segment.id +
                '" href="#" class="translated"' + disabled + ' >' + button_label + '</a><p>' +
                ((UI.isMac) ? 'CMD' : 'CTRL') + '+ENTER</p></li>';

            var buttonsOb = $('#segment-' + segment.id + '-buttons');

            // HACK, remove all but the react-buttons
            buttonsOb.append(UI.segmentButtons);
            buttonsOb.before('<p class="warnings"></p>');

            UI.segmentButtons = null ;
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
            console.log( 'createButtons');

            var data = MateCat.db.segments.by('sid', segment.id);

            if ( data.status.toLowerCase() == 'rejected' ||
                 data.status.toLowerCase() == 'fixed' ||
                 data.status.toLowerCase() == 'rebutted'
               ) {

                var mountpoint = segment.el.find('[data-mount="main-buttons"]')[0];

                ReactDOM.render( React.createElement( MC.SegmentMainButtons, {
                    status: data.status,
                    sid : data.sid
                } ), mountpoint );

            } else {
                unmountReactButtons( segment.el );
                UI.cleanupLegacyButtons( segment );
                UI.createLegacyButtons( segment ) ;
            }
        }
    })

})(jQuery, window);
