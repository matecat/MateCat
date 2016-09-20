if ( ReviewImproved.enabled() && config.isReview ) {
(function($, root, undefined) {

    var originalBindShortcuts = UI.bindShortcuts;

    UI.shortcuts = UI.shortcuts || {} ;

    $.extend(UI.shortcuts, {
        "reject": {
            "label" : "Reject translation",
            "equivalent": "click on Rejected",
            "keystrokes" : {
                "standard": "ctrl+shift+down",
                "mac": "meta+shift+down"
            }
        }
    });

    var rejectKeyDownEvent = function(e) {
        e.preventDefault();

        if ( $('.button-reject:visible').length ) {
            UI.rejectAndGoToNext();
        }
    }

    $.extend(UI, {

        alertNotTranslatedMessage : "This segment is not translated yet.<br /> Only translated or post-edited segments can be revised. " +
         " <br />If needed, you can force the status by clicking on the coloured bar on the right of the segment ",

        /**
         * Search for the next translated segment to propose for revision.
         * This function searches in the current UI first, then falls back
         * to invoke the server and eventually reload the page to the new
         * URL.
         */
        openNextTranslated: function (sid) {
            sid = sid || UI.currentSegmentId;
            var el = $('#segment-' + sid);

            var translatedList = [];
            var approvedList = [];
            var clickableSelector = UI.targetContainerSelector();

            var clickSegmentIfFound =  function() {
                if( !$(this).is(UI.currentSegment) ) {
                translatedList = $(this);
                translatedList.first().find(UI.targetContainerSelector()).click();
                return false;
                }
            }

            // find in next segments in the current file
            if ( el.nextAll('section.status-translated, section.status-approved').length ) {
                translatedList = el.nextAll('.status-translated');
                approvedList   = el.nextAll('.status-approved');
                if ( translatedList.length ) {
                    translatedList.first().find( clickableSelector ).click();
                } else {
                    approvedList.first().find( clickableSelector ).click();
                }

            } else if(el.parents('article').nextAll('section.status-translated, section.status-approved').length) {
                // find in next segments in the next files
                file = el.parents('article');
                file.nextAll('section.status-translated, section.status-approved').each( clickSegmentIfFound );

                // else find from the beginning of the currently loaded segments in all files
            } else if ($('section.status-translated, section.status-approved').length) {
                // else find from the beginning of the currently loaded segments in all files
                $('section.status-translated, section.status-approved').each( clickSegmentIfFound );

            } else { // find in not loaded segments
                // Go to the next segment saved before
                var callback = function() {
                    $(window).off('modalClosed');
                    //Check if the next is inside the view, if not render the file
                    var next = UI.Segment.findEl(UI.nextUntranslatedSegmentIdByServer);
                    if (next.length > 0) {
                        UI.gotoSegment(UI.nextUntranslatedSegmentIdByServer);
                    } else {
                        UI.renderAfterConfirm(UI.nextUntranslatedSegmentIdByServer);
                    }
                };
                // If the modal is open wait the close event
                if( $(".modal[data-type='confirm']").length ) {
                    $(window).on('modalClosed', function(e) {
                        callback();
                    });
                } else {
                    callback();
                }

            }

        },
        /**
         * translationIsToSave
         *
         * only check if translation is queued
         */
        translationIsToSave : function( segment ) {
            var alreadySet = UI.alreadyInSetTranslationTail( segment.id );
            return !alreadySet ;
        },
        deleteTranslationIssue : function( context ) {
            console.debug('delete issue', context);

            var parsed = JSON.parse( context );
            var issue_path = sprintf(
                '/api/v2/jobs/%s/%s/segments/%s/translation-issues/%s',
                config.id_job, config.password,
                parsed.id_segment,
                parsed.id_issue
            );

            $.ajax({
                url: issue_path,
                type: 'DELETE'
            }).done( function( data ) {
                var record = MateCat.db.segment_translation_issues
                    .by('id', parsed.id_issue);
                MateCat.db.segment_translation_issues.remove( record );
                root.ReviewImproved.reloadQualityReport();
            });
        },
        createButtons: function(segment) {
            root.ReviewImproved.renderButtons();
            UI.currentSegment.trigger('buttonsCreation');

        },
        copySuggestionInEditarea : function() {
            return ;
        },
        targetContainerSelector : function() {
            return '.errorTaggingArea';
        },

        getSegmentTarget : function() {
            // read status from DOM? wrong approach, read from
            // database instead
            var segment = db.segments.by('sid', sid);
            var translation =  segment.translation ;

            return translation ;
        },
        getSegmentMarkup : function() {
            var segmentData = arguments[0];
            var data = UI.getSegmentTemplateData.apply( this, arguments ) ;

            var section            = $( UI.getSegmentTemplate()( data ) );
            var segment_body       = $( MateCat.Templates[ 'review_improved/segment_body' ](data) );
            var textarea_container = MateCat.Templates[ 'review_improved/text_area_container' ](
                {
                    decoded_translation : data.decoded_translation
                });

            segment_body
                .find('[data-mount="segment_text_area_container"]')
                .html( textarea_container );

            section
                .find('[data-mount="segment_body"]')
                .html( segment_body );

            return section[0].outerHTML ;
        },

        getSegmentTemplate : function() {
            return MateCat.Templates['review_improved/segment'];
        },

        rejectAndGoToNext : function() {
            UI.setTranslation({
                id_segment: UI.currentSegmentId,
                status: 'rejected',
                caller: false,
                byStatus: false,
                propagate: false,
            });

            UI.gotoNextSegment() ;
        },

        bindShortcuts : function() {

            originalBindShortcuts();

            $('body').on('keydown.shortcuts', null, UI.shortcuts.reject.keystrokes.standard, rejectKeyDownEvent ) ;
            $('body').on('keydown.shortcuts', null, UI.shortcuts.reject.keystrokes.mac, rejectKeyDownEvent ) ;
        },

        renderAfterConfirm: function (nextId) {
            this.render({
                firstLoad: false,
                segmentToOpen: nextId
            });
        }
    });



  })(jQuery, window, ReviewImproved) ;
}
