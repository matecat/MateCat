if ( ReviewImproved.enabled() && config.isReview ) {
(function($, root, undefined) {

    var originalBindShortcuts = UI.bindShortcuts;
    var originalSetShortcuts = UI.setShortcuts;

    var rejectKeyDownEvent = function(e) {
        e.preventDefault();

        if ( $('.button-reject:visible').length ) {
            UI.rejectAndGoToNext();
        }
    }

    $.extend(UI, {



        alertNotTranslatedMessage : "This segment is not translated yet.<br /> Only translated or post-edited segments can be revised. " +
         " <br />If needed, you can force the status by clicking on the coloured bar on the right of the segment ",

        setShortcuts: function () {
            originalSetShortcuts.apply(this);

            UI.shortcuts.cattol.events.reject = {
                "label" : "Reject translation",
                    "equivalent": "click on Rejected",
                    "keystrokes" : {
                    "standard": "ctrl+shift+down",
                        "mac": "meta+shift+down"
                }
            };
        },

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
                config.id_job, config.review_password,
                parsed.id_segment,
                parsed.id_issue
            );

            $.ajax({
                url: issue_path,
                type: 'DELETE'
            }).done( function( data ) {
                var record = MateCat.db.segment_translation_issues
                    .by('id', parseInt(parsed.id_issue));
                MateCat.db.segment_translation_issues.remove( record );
                root.ReviewImproved.reloadQualityReport();
                UI.reloadQualityReport();
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

        /**
         * Never ask for propagation when in revise page
         * @returns {boolean}
         */
        shouldSegmentAutoPropagate : function() {
            return false;
        },

        getSegmentTarget : function( seg ) {
            var segment = db.segments.by('sid', UI.getSegmentId( seg ) );
            var translation =  segment.translation ;

            return translation ;
        },
        // renderSegments: function (segments, justCreated, fid, where) {
        //
        //     if((typeof this.split_points_source == 'undefined') || (!this.split_points_source.length) || justCreated) {
        //         if ( !this.SegmentsContainers || !this.SegmentsContainers[fid] ) {
        //             if (!this.SegmentsContainers) {
        //                 this.SegmentsContainers = [];
        //             }
        //             var mountPoint = $(".article-segments-container-" + fid)[0];
        //             this.SegmentsContainers[fid] = ReactDOM.render(React.createElement(SegmentsContainer, {
        //                 fid: fid,
        //                 isReviewImproved: true,
        //                 enableTagProjection: UI.enableTagProjection,
        //                 decodeTextFn: UI.decodeText,
        //                 tagModesEnabled: UI.tagModesEnabled,
        //                 speech2textEnabledFn: Speech2Text.enabled,
        //             }), mountPoint);
        //             SegmentActions.renderSegments(segments, fid);
        //         } else {
        //             SegmentActions.addSegments(segments, fid, where);
        //         }
        //         UI.registerFooterTabs();
        //     }
        // },
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

            $('body').on('keydown.shortcuts', null, UI.shortcuts.cattol.events.reject.keystrokes.standard, rejectKeyDownEvent ) ;
            $('body').on('keydown.shortcuts', null, UI.shortcuts.cattol.events.reject.keystrokes.mac, rejectKeyDownEvent ) ;
        },

        renderAfterConfirm: function (nextId) {
            this.render({
                segmentToOpen: nextId
            });
        },
        unlockIceSegment: function (elem) {
            elem.removeClass('locked').removeClass('icon-lock').addClass('unlocked').addClass('icon-unlocked3');
            var section = elem.closest('section');
            section.removeClass('ice-locked').removeClass('readonly').addClass('ice-unlocked');
            section.find('.targetarea').click();
        },
        lockIceSegment: function (elem) {
            elem.removeClass('unlocked').removeClass('icon-unlocked3').addClass('locked').addClass('icon-lock');
            var section = elem.closest('section');
            section.addClass('ice-locked').addClass('readonly').removeClass('ice-unlocked');
            UI.closeSegment(section, 1);
        },

        registerReviseTab: function () {
            return false;
        },

        submitIssues: function (sid, data) {
             return ReviewImproved.submitIssue(sid, data);
        }

    });



  })(jQuery, window, ReviewImproved) ;
}
