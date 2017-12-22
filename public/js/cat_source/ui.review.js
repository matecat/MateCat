/*
 Component: ui.review
 */

Review = {
    enabled : function() {
        return config.enableReview && config.isReview ;
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
    }
});

if ( Review.enabled() )

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


    (function(Review, $, undefined) {

        $.extend(Review, {
            evalOpenableSegment : function(section) {
                if ( isTranslated(section) ) return true ;
                var sid = UI.getSegmentId( section );
                alertNotTranslatedYet( sid ) ;
                $(document).trigger('review:unopenableSegment', section);
                return false ;
            },
        });

        $.extend(UI, {

            alertNotTranslatedMessage : "This segment is not translated yet.<br /> Only translated segments can be revised.",

            registerReviseTab: function () {
                SegmentActions.registerTab('review', true, true);
            },

            trackChanges: function (editarea) {
                var source = UI.currentSegment.find('.original-translation').html();
                source = UI.clenaupTextFromPleaceholders( source );

                var target = $(editarea).text();
                var diffHTML = trackChangesHTML( source, htmlEncode(target) );

                $('.editor .sub-editor.review .track-changes p').html( diffHTML );
            },

            setReviewErrorData: function (d) {
                $.each(d, function (index) {

                    if(this.type == "Typing") $('.editor .error-type input[name=t1][value=' + this.value + ']').prop('checked', true);
                    if(this.type == "Translation") $('.editor .error-type input[name=t2][value=' + this.value + ']').prop('checked', true);
                    if(this.type == "Terminology") $('.editor .error-type input[name=t3][value=' + this.value + ']').prop('checked', true);
                    if(this.type == "Language Quality") $('.editor .error-type input[name=t4][value=' + this.value + ']').prop('checked', true);
                    if(this.type == "Style") $('.editor .error-type input[name=t5][value=' + this.value + ']').prop('checked', true);

                });

            },

            openNextTranslated: function (sid) {
                sid = sid || UI.currentSegmentId;
                var el = $('#segment-' + sid);

                var translatedList = [];
                // find in next segments in the current file
                if(el.nextAll('.status-translated').length) {
                    translatedList = el.nextAll('.status-translated');
                    if( translatedList.length ) {
                        translatedList.first().find(UI.targetContainerSelector()).click();
                    }
                    // find in next segments in the next files
                } else if(el.parents('article').nextAll('section.status-translated').length) {

                    file = el.parents('article');
                    file.nextAll('section.status-translated').each(function () {
                        if (!$(this).is(UI.currentSegment)) {
                            translatedList = $(this);
                            translatedList.first().find(UI.targetContainerSelector()).click();
                            return false;
                        }
                    });
                    // else find from the beginning of the currently loaded segments in all files
                } else if ($('section.status-translated').length) {
                    $('section.status-translated').each(function () {
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
            }
        });
    })(Review, jQuery);

/**
 * Events
 *
 * Only bind events for specific review type
 */

if ( Review.enabled() && (Review.type === 'simple' || Review.type === 'extended' )) {

    $('html').on('open', 'section', function() {
        if($(this).hasClass('opened')) {
            $(this).find('.tab-switcher-review').click();
        }
    }).on('buttonsCreation', 'section', function() {
            UI.overrideButtonsForRevision();
    }).on('click', '.editor .tab-switcher-review', function(e) {
        e.preventDefault();

        $('.editor .submenu .active').removeClass('active');
        $(this).addClass('active');
        $('.editor .sub-editor.open').removeClass('open');
        if($(this).hasClass('untouched')) {
            $(this).removeClass('untouched');
            if(!UI.body.hasClass('hideMatches')) {
                $('.editor .sub-editor.review').addClass('open');
            }
        } else {
            $('.editor .sub-editor.review').addClass('open');
        }
    }).on('input', '.editor .editarea', function() {
        /*UI.trackChanges(this);*/
    }).on('afterFormatSelection', '.editor .editarea', function() {
        UI.trackChanges(this);
    }).on('click', '.editor .outersource .copy', function(e) {
        UI.trackChanges(UI.editarea);
    }).on('click', 'a.approved, a.next-unapproved', function(e) {
        // the event click: 'A.APPROVED' i need to specify the tag a and not only the class
        // because of the event is triggered even on download button
        UI.clickOnApprovedButton(e, this)
    }).on('click', '.sub-editor.review .error-type input[type=radio]', function(e) {
        $('.sub-editor.review .error-type').removeClass('error');
    }).on('setCurrentSegment_success', function(e, d, id_segment) {
        UI.addOriginalTranslation(d, id_segment);
    });

    $.extend(UI, {
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
        /**
         *
         * @param d Data response from the SetCurrentSegment request
         * @param id_segment
         */
        addOriginalTranslation: function (d, id_segment) {
            var xEditarea = $('#segment-' + id_segment + '-editarea');
            if (d.original !== '') {
                SegmentActions.addOriginalTranslation(id_segment, UI.getSegmentFileId($('#segment-' + id_segment)), d.original);
            }
            if ( Review.type === 'simple') {
                UI.setReviewErrorData(d.error_data);
                setTimeout(function () {
                    UI.trackChanges(xEditarea);
                }, 100);
            }
        },

        setReviewErrorData: function (d) {
            $.each(d, function (index) {
                if(this.type == "Typing") $('.editor .error-type input[name=t1][value=' + this.value + ']').prop('checked', true);
                if(this.type == "Translation") $('.editor .error-type input[name=t2][value=' + this.value + ']').prop('checked', true);
                if(this.type == "Terminology") $('.editor .error-type input[name=t3][value=' + this.value + ']').prop('checked', true);
                if(this.type == "Language Quality") $('.editor .error-type input[name=t4][value=' + this.value + ']').prop('checked', true);
                if(this.type == "Style") $('.editor .error-type input[name=t5][value=' + this.value + ']').prop('checked', true);

            });

        },

        renderAfterConfirm: function (nextId) {
            this.render({
                segmentToOpen: nextId
            });
        },
        clickOnApprovedButton: function (e, button) {
            // the event click: 'A.APPROVED' i need to specify the tag a and not only the class
            // because of the event is triggered even on download button
            e.preventDefault();
            var goToNextNotApproved = ($(button).hasClass('approved')) ? false : true;
            UI.tempDisablingReadonlyAlert = true;
            SegmentActions.removeClassToSegment(UI.getSegmentId( UI.currentSegment ), 'modified');
            UI.currentSegment.data('modified', false);


            $('.sub-editor.review .error-type').removeClass('error');

            UI.changeStatus(button, 'approved', 0);  // this does < setTranslation

            var original = UI.currentSegment.find('.original-translation').text();
            var sid = UI.currentSegmentId;
            var err = $('.sub-editor.review .error-type');
            var err_typing = $(err).find('input[name=t1]:checked').val();
            var err_translation = $(err).find('input[name=t2]:checked').val();
            var err_terminology = $(err).find('input[name=t3]:checked').val();
            var err_language = $(err).find('input[name=t4]:checked').val();
            var err_style = $(err).find('input[name=t5]:checked').val();

            if (goToNextNotApproved) {
                UI.openNextTranslated();
            } else {
                UI.gotoNextSegment();
            }

            var data = {
                action: 'setRevision',
                job: config.job_id,
                jpassword: config.password,
                segment: sid,
                original: original,
                err_typing: err_typing,
                err_translation: err_translation,
                err_terminology: err_terminology,
                err_language: err_language,
                err_style: err_style
            };

            UI.setRevision(data);
        },
        overrideButtonsForRevision: function () {
            var div = $('<ul>' + UI.segmentButtons + '</ul>');

            div.find('.translated').text('APPROVED').removeClass('translated').addClass('approved');
            var nextSegment = UI.currentSegment.next();
            var goToNextApprovedButton = !nextSegment.hasClass('status-translated');
            div.find('.next-untranslated').parent().remove();
            if (goToNextApprovedButton) {
                var htmlButton = '<li><a id="segment-' + this.currentSegmentId +
                    '-nexttranslated" href="#" class="btn next-unapproved" data-segmentid="segment-' +
                    this.currentSegmentId + '" title="Revise and go to next translated"> A+&gt;&gt;</a><p>' +
                    ((UI.isMac) ? 'CMD' : 'CTRL') + '+SHIFT+ENTER</p></li>';
                div.find('.approved').parent().prepend(htmlButton);
            }
            UI.segmentButtons = div.html();
        }

    });
}
