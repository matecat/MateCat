/*
 Component: ui.review
 */
Review = {
    enabled : function() {
        return (config.enableReview && !!config.isReview);
    },
    type : config.reviewType
};
$.extend( UI, {
    clenaupTextFromPleaceholders : function(text) {
        text = text
            .replace( config.lfPlaceholderRegex, "\n" )
            .replace( config.crPlaceholderRegex, "\r" )
            .replace( config.crlfPlaceholderRegex, "\r\n" )
            .replace( config.tabPlaceholderRegex, "\t" )
            .replace( config.nbspPlaceholderRegex, String.fromCharCode( parseInt( 0xA0, 10 ) ) );
        return text;
    },
    evalOpenableSegment: function ( section ) {
        if ( isTranslated( section ) ) return true;
        var sid = UI.getSegmentId( section );
        if ( UI.projectStats && UI.projectStats.TRANSLATED_PERC === 0 ) {
            alertNoTranslatedSegments()
        } else {
            alertNotTranslatedYet( sid );
        }
        $( document ).trigger( 'review:unopenableSegment', section );
        return false;
    },
    reloadQualityReport : function() {
        var path  = sprintf(APP.getRandomUrl() + 'api/app/jobs/%s/%s/quality-report',
            config.id_job, config.password);
        $.ajax( {
            type: "GET",
            xhrFields: {withCredentials: true},
            url: path
        })
            .done( function( data ) {
                var revNumber = (config.revisionNumber) ?  config.revisionNumber : 1;
                var review = data['quality-report'].chunk.reviews.find(function ( value ) {
                    return value.revision_number === revNumber;
                }) ;

                window.quality_report_btn_component.setState({
                    is_pass : review.is_pass,
                    score : review.score
                });
            });
    }
});

var alertNotTranslatedYet = function( sid ) {
    APP.confirm({
        name: 'confirmNotYetTranslated',
        cancelTxt: 'Close',
        callback: 'openNextTranslated',
        okTxt: 'Open next translated segment',
        context: sid,
        msg: UI.alertNotTranslatedMessage
    });
};

var alertNoTranslatedSegments = function(  ) {
    var props = {
        text: 'There are no translated segments to revise in this job.',
        successText: "Ok",
        successCallback: function() {
            APP.ModalWindow.onCloseModal();
        }
    };
    APP.ModalWindow.showModalComponent(ConfirmMessageModal, props, "Warning");
};


if ( config.enableReview && config.isReview ) {



    (function($, undefined) {

        /**
         * Events
         *
         * Only bind events for specific review type
         */
        $('html').on('buttonsCreation', 'section', function() {
            UI.overrideButtonsForRevision();
        }).on('afterFormatSelection', '.editor .editarea', function() {
            UI.trackChanges(this);
        }).on('click', '.editor .outersource .copy', function(e) {
            UI.trackChanges(UI.editarea);
        }).on('click', 'a.approved, a.next-unapproved', function(e) {
            // the event click: 'A.APPROVED' i need to specify the tag a and not only the class
            // because of the event is triggered even on download button
            UI.clickOnApprovedButton(e, this)
        }).on('click', 'a.next-review-repetition', function(e) {
            e.preventDefault();
            SegmentFilter.goToNextRepetition(this, 'approved');
        }).on('click', 'a.next-review-repetition-group', function(e) {
            e.preventDefault();
            SegmentFilter.goToNextRepetitionGroup(this, 'approved');
        }).on('setCurrentSegment_success', function(e, d, id_segment) {
            UI.addOriginalTranslation(d, id_segment);
        });


        $.extend(UI, {

            alertNotTranslatedMessage : "This segment is not translated yet.<br /> Only translated segments can be revised.",

            trackChanges: function (editarea) {
                var $segment = $(editarea).closest('section');
                var source = UI.postProcessEditarea($segment, '.original-translation');
                source = UI.clenaupTextFromPleaceholders( source );
                //Fix for &amp in original-translation
                source = source.replace(/&amp;/g, "&");

                var target = UI.postProcessEditarea($segment, '.targetarea');
                target = UI.clenaupTextFromPleaceholders( target );
                var diffHTML = trackChangesHTML( htmlEncode(source), htmlEncode(target) );
                diffHTML = UI.transformTextForLockTags(diffHTML);
                $('.sub-editor.review .track-changes p', $segment).html( diffHTML );
            },
            openNextTranslated: function (sid) {
                sid = sid || UI.currentSegmentId;
                var el = $('#segment-' + sid);

                var translatedList = [];
                var nextSegmentSelector = this.getSelectorForNextSegment();
                // find in next segments in the current file
                if(el.nextAll(nextSegmentSelector).length) {
                    translatedList = el.nextAll(nextSegmentSelector);
                    if( translatedList.length ) {
                        translatedList.first().find(UI.targetContainerSelector()).click();
                    }
                    // find in next segments in the next files
                } else if(el.parents('article').nextAll(nextSegmentSelector).length) {

                    file = el.parents('article');
                    file.nextAll(nextSegmentSelector).each(function () {
                        if (!$(this).is(UI.currentSegment)) {
                            translatedList = $(this);
                            translatedList.first().find(UI.targetContainerSelector()).click();
                            return false;
                        }
                    });
                    // else find from the beginning of the currently loaded segments in all files
                } else if ($(nextSegmentSelector).length) {
                    $(nextSegmentSelector).each(function () {
                        if (!$(this).is(UI.currentSegment)) {
                            translatedList = $(this);
                            translatedList.first().find(UI.targetContainerSelector()).click();
                            return false;
                        }
                    });
                } else { // find in not loaded segments or go to the next approved
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
            getSelectorForNextSegment: function() {
                return 'section.status-translated'
            },
            setRevision: function( data ){
                APP.doRequest({
                    data: data,
                    error: function() {
                        UI.failedConnection( data, 'setRevision' );
                    },
                    success: function(d) {
                        window.quality_report_btn_component.setState({
                            vote: d.data.overall_quality_class
                        });
                    }
                });
            },
            renderAfterConfirm: function (nextId) {
                this.render({
                    segmentToOpen: nextId
                });
            },
            /**
             * Each revision overwrite this function
             * @param e
             * @param button
             */
            clickOnApprovedButton: function (e, button) {
                return false
            },
            overrideButtonsForRevision: function () {
                var div = $('<ul>' + UI.segmentButtons + '</ul>');

                div.find('.translated').text('APPROVED').removeClass('translated').addClass('approved');
                var nextSegment = UI.currentSegment.next();
                var goToNextApprovedButton = !nextSegment.hasClass('status-translated');
                var filtering = (SegmentFilter.enabled() && SegmentFilter.filtering() && SegmentFilter.open);
                div.find('.next-untranslated').parent().remove();
                div.find('.next-repetition').removeClass('next-repetition').addClass('next-review-repetition').removeClass('primary').addClass('green');
                div.find('.next-repetition-group').removeClass('next-repetition-group').addClass('next-review-repetition-group').removeClass('primary').addClass('green');
                if (goToNextApprovedButton && !filtering) {
                    var htmlButton = '<li><a id="segment-' + this.currentSegmentId +
                        '-nexttranslated" href="#" class="btn next-unapproved" data-segmentid="segment-' +
                        this.currentSegmentId + '" title="Revise and go to next translated"> A+&gt;&gt;</a><p>' +
                        ((UI.isMac) ? 'CMD' : 'CTRL') + '+SHIFT+ENTER</p></li>';
                    div.html(htmlButton + div.html());
                }
                UI.segmentButtons = div.html();
            }
        });
    })(jQuery);
}
