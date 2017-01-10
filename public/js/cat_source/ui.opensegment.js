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

            this.activateSegment(segment);

            segment.el.trigger('open');
            
            $('section').first().nextAll('.undoCursorPlaceholder').remove();
            this.getNextSegment(this.currentSegment, 'untranslated');

            // if ((!this.readonly)&&(!getNormally)) {
            //     $('#segment-' + segment.id + ' .alternatives .overflow').hide();
            // }
            this.setCurrentSegment();

            if (!this.readonly) {
                this.getContribution(segment.el, 0);
            }

            SegmentActions.addClassToSegment(UI.getSegmentId(this.currentSegment), 'opened');
            this.currentSegment.attr('data-searchItems', ($('mark.searchMarker', this.editarea).length));

            this.fillCurrentSegmentWarnings(this.globalWarnings, true);
            // this.setNextWarnedSegment();

            this.focusEditarea = setTimeout(function() {
                UI.editarea.focus();
                clearTimeout(UI.focusEditarea);
                UI.currentSegment.trigger('EditAreaFocused');
            }, 100);

            if(!this.noGlossary) this.getGlossary(segment.el, true, 0);

            UI.setEditingSegment( segment.el );

            this.opening = true;

            if (!(this.currentSegment.is(this.lastOpenedSegment))) {
                var lastOpened = $(this.lastOpenedSegment).attr('id');
                if (lastOpened != 'segment-' + this.currentSegmentId)
                    this.closeSegment(this.lastOpenedSegment, 0, operation);
            }

            this.opening = false;


            SegmentActions.addClassToSegment(UI.getSegmentId(segment), 'editor');

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

            if (!this.readonly) {
                this.getContribution(segment.el, 1);
                this.getContribution(segment.el, 2);

                if(!this.noGlossary) this.getGlossary(segment.el, true, 1);
                if(!this.noGlossary) this.getGlossary(segment.el, true, 2);
            }

            $(window).trigger({
                type: "segmentOpened",
                segment: segment
            });

            Speech2Text.enabled() && Speech2Text.enableMicrophone(segment.el);
        }
    });
})(jQuery, UI);
