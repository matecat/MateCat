(function($, UI, undefined) {

    $.extend(UI, {
        openSegment: function(editarea_or_segment, operation) {
            var editarea, segment;
            if ( editarea_or_segment instanceof UI.Segment ) {
                editarea = $('.editarea', editarea_or_segment.el);
                segment = editarea_or_segment ;
            }
            else {
                editarea = $(editarea_or_segment) ;
                segment = new UI.Segment( editarea.closest('section') );
            }

            if ( Review.enabled() && !Review.evalOpenableSegment( segment.el ) ) {
                return false ;
            }

            this.openSegmentStart = new Date();

            if (UI.warningStopped) {
                UI.warningStopped = false;
                UI.checkWarnings(false);
            }
            if (!this.byButton) {
                if (this.justSelecting('editarea'))
                    return;
            }

            this.numOpenedSegments++;
            this.firstOpenedSegment = (this.firstOpenedSegment === 0) ? 1 : 2;
            this.byButton = false;

            this.cacheObjects( segment );

            this.updateJobMenu();

            this.clearUndoStack();

            if ( editarea.length > 0 ) this.saveInUndoStack('open');

            this.autoSave = true;

            var s1 = $('#segment-' + this.lastTranslatedSegmentId + ' .source').text();
            var s2 = $('.source', segment.el).text();
            var isNotSimilar = lev(s1,s2)/Math.max(s1.length,s2.length)*100 >50;
            var isEqual = (s1 == s2);

            getNormally = isNotSimilar || isEqual;

            this.activateSegment(segment.el, getNormally);

            segment.el.trigger('open');
            
            $('section').first().nextAll('.undoCursorPlaceholder').remove();
            this.getNextSegment(this.currentSegment, 'untranslated');

            if ((!this.readonly)&&(!getNormally)) {
                $('#segment-' + segment.id + ' .alternatives .overflow').hide();
            }
            this.setCurrentSegment();

            if (!this.readonly) {

                if(getNormally) {
                    this.getContribution(segment.el, 0);
                } else {
                    console.log('riprova dopo 3 secondi');
                    $(segment.el).removeClass('loaded');
                    $(".loader", segment.el).addClass('loader_on');
                    setTimeout(function() {
                        $('.alternatives .overflow', segment.el).show();
                        UI.getContribution(segment.el, 0);
                    }, 3000);
                }
            }

            this.currentSegment.addClass('opened');

            this.currentSegment.attr('data-searchItems', ($('mark.searchMarker', this.editarea).length));

            this.fillCurrentSegmentWarnings(this.globalWarnings, true);
            this.setNextWarnedSegment();

            this.focusEditarea = setTimeout(function() {
                UI.editarea.focus();
                clearTimeout(UI.focusEditarea);
                UI.currentSegment.trigger('EditAreaFocused');
            }, 100);

            this.currentIsLoaded = false;
            this.nextIsLoaded = false;

            if(!this.noGlossary) this.getGlossary(segment.el, true, 0);

            UI.setEditingSegment( segment.el );

            this.opening = true;

            if (!(this.currentSegment.is(this.lastOpenedSegment))) {
                var lastOpened = $(this.lastOpenedSegment).attr('id');
                if (lastOpened != 'segment-' + this.currentSegmentId)
                    this.closeSegment(this.lastOpenedSegment, 0, operation);
            }

            this.opening = false;


            segment.el.addClass("editor");

            if (!this.readonly) {
                /* Check if is right-to-left language, because there is a bug that make
                    Chrome crash, this happens without the timer */
                if (this.body.hasClass('rtl-target')) {
                    setTimeout(function () {
                        UI.editarea.attr('contenteditable', 'true');
                    }, 500);
                } else {
                    UI.editarea.attr('contenteditable', 'true');
                }
            }

            this.editStart = new Date();

            $(editarea).removeClass("indent");

            this.lockTags();

            if (!this.readonly) {
                this.getContribution(segment.el, 1);
                this.getContribution(segment.el, 2);

                if(!this.noGlossary) this.getGlossary(segment.el, true, 1);
                if(!this.noGlossary) this.getGlossary(segment.el, true, 2);
            }

            if (this.debug)
                console.log('close/open time: ' + ((new Date()) - this.openSegmentStart));

            $(window).trigger({
                type: "segmentOpened",
                segment: segment
            });

            Speech2Text.enabled() && Speech2Text.enableMicrophone(segment.el);
        }
    });
})(jQuery, UI);
