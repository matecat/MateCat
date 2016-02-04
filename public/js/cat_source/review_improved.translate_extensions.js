if ( ReviewImproved.enabled() && !config.isReview)
(function($, root, undefined) {

    $.extend(UI, {
        createButtons: function(segment) {
            var segment_data = MateCat.colls.segments.find({ sid: segment.id})[0];

            console.log( segment_data );

            if ( segment_data && segment_data.status == 'REJECTED' ) {
                // rejected
            } else {

                var button_label = config.status_labels.TRANSLATED ;
                var label_first_letter = button_label[0];

                var disabled = (this.currentSegment.hasClass('loaded')) ? '' : ' disabled="disabled"';
                var nextSegment = this.currentSegment.next();
                var sameButton = (nextSegment.hasClass('status-new')) || (nextSegment.hasClass('status-draft'));
                var nextUntranslated = (sameButton)? '' : '<li><a id="segment-' + this.currentSegmentId +
                '-nextuntranslated" href="#" class="btn next-untranslated" data-segmentid="segment-' +
                this.currentSegmentId + '" title="Translate and go to next untranslated">' +
                label_first_letter + '+&gt;&gt;</a><p>' +
                ((UI.isMac) ? 'CMD' : 'CTRL') + '+SHIFT+ENTER</p></li>';
                UI.segmentButtons = nextUntranslated + '<li><a id="segment-' + this.currentSegmentId +
                    '-button-translated" data-segmentid="segment-' + this.currentSegmentId +
                    '" href="#" class="translated"' + disabled + ' >' + button_label + '</a><p>' +
                    ((UI.isMac) ? 'CMD' : 'CTRL') + '+ENTER</p></li>';

                var buttonsOb = $('#segment-' + this.currentSegmentId + '-buttons');

                buttonsOb.empty().append(UI.segmentButtons);
                buttonsOb.before('<p class="warnings"></p>');

                UI.segmentButtons = null;

            }

        }
    })

})(jQuery, window);
