/*
 Component: ui.review
 */
if(config.enableReview && parseInt(config.isReview)) {

    $('html').on('open', 'section', function() {
        editarea = $(this).find('.editarea');
        editarea.after('<div class="original-translation" style="display: none">' + $(this).find('.editarea').text() + '</div>');
    }).on('buttonsCreation', 'section', function() {
        var div = $('<ul>' + UI.segmentButtons + '</ul>');

        div.find('.translated').text('APPROVED').removeClass('translated').addClass('approved');
        div.find('.next-untranslated').parent().remove();

        UI.segmentButtons = div.html();
    }).on('footerCreation', 'section', function() {
        var div = $('<div>' + UI.footerHTML + '</div>');

        div.find('.submenu').append('<li class="tab-switcher-review" id="segment-20896069-review"><a tabindex="-1" href="#">Review</a></li>');
        div.append('<div class="tab sub-editor review" id="segment-' + this.currentSegmentId + '-review">' + $('#tpl-review-tab').html() + '</div>');
        $('.tab-switcher-review').click();
        setTimeout(function() {// fixes a bug in setting defaults in radio buttons
            UI.currentSegment.find('.sub-editor.review .error-type input[value=0]').click();
            UI.trackChanges(UI.editarea);
        }, 100);
        UI.footerHTML = div.html();

    }).on('click', '.editor .tab-switcher-review', function(e) {
        e.preventDefault();
        $('.editor .submenu .active').removeClass('active');
        $(this).addClass('active');
        $('.editor .sub-editor').hide();
        $('.editor .sub-editor.review').show();
    }).on('input', '.editor .editarea', function() {
        UI.trackChanges(this);
    }).on('click', '.approved', function(e) {
        e.preventDefault();
        noneSelected = !((UI.currentSegment.find('.sub-editor.review .error-type input[value=1]').is(':checked'))||(UI.currentSegment.find('.sub-editor.review .error-type input[value=2]').is(':checked')));
        if(noneSelected) {
            $('.editor .tab-switcher-review').click();
            $('.sub-editor.review .error-type').addClass('error');
        } else {
            $('.sub-editor.review .error-type').removeClass('error');
            UI.changeStatus(this, 'approved', 0);
//            APP.alert('This will save the translation in the new db field.<br />Feature under construction');
            err = $('.sub-editor.review .error-type');
            APP.doRequest({
//                data: reqData,

                data: {
                    action: 'setRevision',
                    job: config.job_id,
                    segment: UI.currentSegmentId,
                    err_typing: $(err).find('input[name=t1]:checked').val(),
                    err_translation: $(err).find('input[name=t2]:checked').val(),
                    err_terminology: $(err).find('input[name=t3]:checked').val(),
                    err_quality: $(err).find('input[name=t4]:checked').val(),
                    err_style: $(err).find('input[name=t5]:checked').val()
                },

//                context: [reqArguments, segment, status],
                error: function() {
//                    UI.failedConnection(this[0], 'setTranslation');
                },
                success: function(d) {
                    console.log('d: ', d);
                }
            });


        }
//        if(!((UI.currentSegment.find('.sub-editor.review .error-type input[value=1]').is(':checked'))||(UI.currentSegment.find('.sub-editor.review .error-type input[value=2]').is(':checked')))) console.log('sono tutti none');
    }).on('click', '.sub-editor.review .error-type input[type=radio]', function(e) {
        $('.sub-editor.review .error-type').removeClass('error');
    });
    $.extend(UI, {
        trackChanges: function (editarea) {
            var diff = UI.dmp.diff_main(UI.currentSegment.find('.original-translation').text(), $(editarea).text());
            diffTxt = '';
            $.each(diff, function (index) {
                if(this[0] == -1) {
                    diffTxt += '<span class="deleted">' + this[1] + '</span>';
                } else if(this[0] == 1) {
                    diffTxt += '<span class="added">' + this[1] + '</span>';
                } else {
                    diffTxt += this[1];
                }
                $('.editor .sub-editor.review .track-changes p').html(diffTxt);
            });
        }
    })
}