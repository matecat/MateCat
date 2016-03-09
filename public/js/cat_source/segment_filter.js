
SegmentFilter = window.SegmentFilter || {};

SegmentFilter.enabled = function() {
    return true;
}

if (SegmentFilter.enabled())
(function($, UI, SF, undefined) {
    SF.overrides = { }

    SF.overrides.getSegmentsMarkup = UI.getSegmentMarkup ;
    SF.overrides.openSegment       = UI.openSegment ;
    SF.overrides.editAreaClick     = UI.editAreaClick ;
    SF.overrides.rulesForNextSegment = UI.rulesForNextSegment ;
    SF.overrides.rulesForNextUntranslatedSegment = UI.rulesForNextUntranslatedSegment ;
    SF.overrides.evalNextSegment = UI.evalNextSegment ;
    SF.overrides.gotoPreviousSegment = UI.gotoPreviousSegment ;
    SF.overrides.gotoNextSegment = UI.gotoNextSegment ;

    SF.filtering = function() {
        return true;
    },

    $.extend(UI, {
        gotoPreviousSegment: function() {
            var rules = 'section:not(.muted)';
            var prev = $('.editor').prevAll( rules ).first();

            if (prev.is('section')) {
                $(UI.targetContainerSelector(), prev).click();
            } else {
                prev = $('.editor').parents('article').prev().find( rules ).first();
                if (prev.length) {
                    $(UI.targetContainerSelector() , prev).click();
                } else {
                    UI.topReached();
                }
            }
            if (prev.length)
                UI.scrollSegment(prev);
        },

        gotoNextSegment: function() {
            var rules = 'section:not(.muted)';
            var next = $('.editor').nextAll( rules ).first();

            if (next.is('section')) {
                this.scrollSegment(next);
                $(UI.targetContainerSelector(), next).trigger("click", "moving");
            } else {
                next = this.currentFile.next().find( rules ).first();
                if (next.length) {
                    this.scrollSegment(next);
                    $(UI.targetContainerSelector(), next).trigger("click", "moving");
                } else {
                    UI.closeSegment(UI.currentSegment, 1, 'save');
                }
            }
        },

        evalNextSegment: function( section, status ) {
            // Next untranslated segment
            //
            var rules = UI.rulesForNextUntranslatedSegment( status, section );
            var nextUntranslated = $(section).nextAll(rules).first();

            if (!nextUntranslated.length) {
                nextUntranslated = $(section).parents('article').next().find(rules).first();
            }

            if (nextUntranslated.length) {
                UI.nextUntranslatedSegmentId = UI.getSegmentId($(nextUntranslated));
            } else {
                UI.nextUntranslatedSegmentId = UI.nextUntranslatedSegmentIdByServer;
            }

            // Next absolute segment
            //
            var rules = 'section:not(.muted)';
            var next = $(section).nextAll( rules ).first();

            if (!next.length) {
                // TODO: explain what this does.
                next = $(section).parents('article').next().find(rules).first();
            }

            if (next.length) {
                UI.nextSegmentId = UI.getSegmentId($(next));
            } else {
                UI.nextSegmentId = 0;
            }
        },

        rulesForNextUntranslatedSegment : function(status, section) {
            var rules = 'section:not(.muted)';
            return rules ;
        },

        isMuted : function(el) {
            return  $(el).closest('section').hasClass('muted');
        },

        editAreaClick : function(e, operation, action) {
            var e = arguments[0];
            if ( ! UI.isMuted(e.target) ) {
                SF.overrides.editAreaClick.apply( e.target, arguments );
            }
        },

        getSegmentMarkup : function() {
            var markup = SF.overrides.getSegmentsMarkup.apply( undefined, arguments );
            var segment = arguments[0];
            if ( parseInt( segment.sid ) % 2 == 1 ) {
                markup = $(markup).addClass('muted');
                markup = $('<div/>').append(markup).html();
            }

            return markup ;
        }
    });


    $(document).on('ready', function() {

    });


})(jQuery, UI, SegmentFilter);
