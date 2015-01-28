/*
 Component: ui.review
 */
if(config.enableReview && parseInt(config.isReview)) {

    $('html').on('open', 'section', function() {
//        editarea = $(this).find('.editarea');
//        editarea.after('<div class="original-translation" style="display: none">' + $(this).find('.editarea').text() + '</div>');
    }).on('start', function() {
        // temp
        config.stat_quality = [
            {
                "type":"Typing",
                "allowed":5,
                "found":1,
                "vote":"Excellent"
            },
            {
                "type":"Translation",
                "allowed":5,
                "found":1,
                "vote":"Excellent"
            },
            {
                "type":"Terminology",
                "allowed":5,
                "found":1,
                "vote":"Excellent"
            },
            {
                "type":"Language Quality",
                "allowed":5,
                "found":1,
                "vote":"Excellent"
            },
            {
                "type":"Style",
                "allowed":5,
                "found":1,
                "vote":"Excellent"
            }
        ];
        // end temp
        $('#statistics .statistics-core').append('<li id="stat-quality">Overall quality: <span class="quality">Fail</span> <a href="#" class="details">(Details)</a></li>');
        UI.createStatQualityPanel();
        UI.populateStatQualityPanel(config.stat_quality);
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
 /*
        setTimeout(function() {// fixes a bug in setting defaults in radio buttons
            UI.currentSegment.find('.sub-editor.review .error-type input[value=0]').click();
            UI.trackChanges(UI.editarea);
        }, 100);
 */
        UI.footerHTML = div.html();

    }).on('click', '.editor .tab-switcher-review', function(e) {
        e.preventDefault();
        $('.editor .submenu .active').removeClass('active');
        $(this).addClass('active');
        $('.editor .sub-editor').hide();
        $('.editor .sub-editor.review').show();
    }).on('input', '.editor .editarea', function() {
        UI.trackChanges(this);
    }).on('click', '.editor .outersource .copy', function(e) {
        UI.trackChanges(UI.editarea);
    }).on('click', '.approved', function(e) {
        e.preventDefault();
/*
        var a = UI.currentSegment.find('.original-translation').text() + '"';
        var b = $(editarea).text() + '"';
        console.log('a: "', htmlEncode(a));
        console.log('b: "', htmlEncode(b));
        console.log('a = b: ', a == b);
        console.log('numero di modifiche: ', $('.editor .track-changes p span').length);

        if(UI.currentSegment.find('.original-translation').text() == $(editarea).text()) console.log('sono uguali');
 */
        noneSelected = !((UI.currentSegment.find('.sub-editor.review .error-type input[value=1]').is(':checked'))||(UI.currentSegment.find('.sub-editor.review .error-type input[value=2]').is(':checked')));
        if((noneSelected)&&($('.editor .track-changes p span').length)) {
            $('.editor .tab-switcher-review').click();
            $('.sub-editor.review .error-type').addClass('error');
        } else {
            original = UI.currentSegment.find('.original-translation').text();
            $('.sub-editor.review .error-type').removeClass('error');
            UI.changeStatus(this, 'approved', 0);
            err = $('.sub-editor.review .error-type');
            err_typing = $(err).find('input[name=t1]:checked').val();
            err_translation = $(err).find('input[name=t2]:checked').val();
            err_terminology = $(err).find('input[name=t3]:checked').val();
            err_quality = $(err).find('input[name=t4]:checked').val();
            err_style = $(err).find('input[name=t5]:checked').val();
            UI.gotoNextSegment();

//            APP.alert('This will save the translation in the new db field.<br />Feature under construction');

            APP.doRequest({
//                data: reqData,

                data: {
                    action: 'setRevision',
                    job: config.job_id,
                    segment: UI.currentSegmentId,
                    original: original,
                    err_typing: err_typing,
                    err_translation: err_translation,
                    err_terminology: err_terminology,
                    err_quality: err_quality,
                    err_style: err_style
                },

//                context: [reqArguments, segment, status],
                error: function() {
//                    UI.failedConnection(this[0], 'setTranslation');
                },
                success: function(d) {
//                    console.log('d: ', d);
                    // temp
                    d.stat_quality = config.stat_quality;
                    d.stat_quality[0].found = 2;
                    //end temp
                    UI.populateStatQualityPanel(d.stat_quality);
                }
            });


        }
//        if(!((UI.currentSegment.find('.sub-editor.review .error-type input[value=1]').is(':checked'))||(UI.currentSegment.find('.sub-editor.review .error-type input[value=2]').is(':checked')))) console.log('sono tutti none');
    }).on('click', '.sub-editor.review .error-type input[type=radio]', function(e) {
        $('.sub-editor.review .error-type').removeClass('error');
    }).on('click', '#stat-quality .details', function(e) {
        e.preventDefault();
        UI.openStatQualityPanel();
    }).on('click', '.popup-stat-quality h1 .btn-ok, .outer-stat-quality', function(e) {
        e.preventDefault();
        $( ".popup-stat-quality").removeClass('open').hide("slide", { direction: "right" }, 400);
        $(".outer-stat-quality").hide();
        $('body').removeClass('side-popup');
    }).on('setCurrentSegment_success', function(e, d) {
        console.log('d: ', d)
        // temp
/*
        d.error_data = [
            {
                "type":"Typing",
                "value": 1
            },
            {
                "type":"Translation",
                "value": 2
            },
            {
                "type":"Terminology",
                "value": 0
            },
            {
                "type":"Language Quality",
                "value": 0
            },
            {
                "type":"Style",
                "value": 0
            }
        ];
        d.original = UI.editarea.text();
        */
        // end temp
        if(d.original == '') d.original = UI.editarea.text();
        UI.editarea.after('<div class="original-translation" style="display: none">' + d.original + '</div>');
        UI.setReviewErrorData(d.error_data);
        UI.trackChanges(UI.editarea);
    });

    $.extend(UI, {
        trackChanges: function (editarea) {
/*
            console.log('11111: ', $(editarea).text());
            console.log('22222: ', htmlEncode($(editarea).text()));
            console.log('a: ', UI.currentSegment.find('.original-translation').text());
            console.log('b: ', $(editarea).html());
            console.log('c: ', $(editarea).text());
            var c = $(editarea).text();
            console.log('d: ', c.replace(/(<([^>]+)>)/ig,""));
*/
            var diff = UI.dmp.diff_main(UI.currentSegment.find('.original-translation').text(), $(editarea).text().replace(/(<([^>]+)>)/ig,""));
//            console.log('diff: ', diff);
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
        },
        createStatQualityPanel: function () {
            UI.body.append('<div id="popup-stat-quality">' + $('#tpl-review-stat-quality').html() + '</div>');
        },
        populateStatQualityPanel: function (d) {
            tbody = $('#popup-stat-quality .slide-panel-body tbody');
            tbody.empty();
            $.each(d, function (index) {
                $(tbody).append('<tr data-vote="' + this.vote.trim() + '"><td>' + this.type + '</td><td>' + this.allowed + '</td><td>' + this.found + '</td><td>' + this.vote + '</td></tr>')
            });
//            UI.body.append('<div id="popup-stat-quality">' + $('#tpl-review-stat-quality').html() + '</div>');
        },
        openStatQualityPanel: function() {
            $('body').addClass('side-popup');
            $(".popup-stat-quality").addClass('open').show("slide", { direction: "right" }, 400);
//            $("#SnapABug_Button").hide();
            $(".outer-stat-quality").show();
//            $.cookie('tmpanel-open', 1, { path: '/' });
        },
        setReviewErrorData: function (d) {
            $.each(d, function (index) {
//                console.log(this.type + ' - ' + this.value);
//                console.log('.editor .error-type input[name=t1][value=' + this.value + ']');
                if(this.type == "Typing") $('.editor .error-type input[name=t1][value=' + this.value + ']').prop('checked', true);
                if(this.type == "Translation") $('.editor .error-type input[name=t2][value=' + this.value + ']').prop('checked', true);
                if(this.type == "Terminology") $('.editor .error-type input[name=t3][value=' + this.value + ']').prop('checked', true);
                if(this.type == "Language Quality") $('.editor .error-type input[name=t4][value=' + this.value + ']').prop('checked', true);
                if(this.type == "Style") $('.editor .error-type input[name=t5][value=' + this.value + ']').prop('checked', true);

            });

        }

    })
}