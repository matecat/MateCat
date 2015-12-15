/*
 Component: ui.review
 */

Review = {
    enabled : function() {
        return config.enableReview && config.isReview ;
    },
};

if ( Review.enabled() )
(function(Review, window) {
    var alertNotTranslatedYet = function( sid ) {
        APP.confirm({
            name: 'confirmNotYetTranslated',
            cancelTxt: 'Close',
            callback: 'openNextTranslated',
            okTxt: 'Open next translated segment',
            context: sid,
            msg: "This segment is not translated yet.<br /> Only translated segments can be revised."
        });
    }

    $.extend(Review, {
        evalOpenableSegment : function(section) {
            if ( isTranslated(section) ) return true ;
            var sid = UI.getSegmentId( section );
            alertNotTranslatedYet( sid ) ;
            $(document).trigger('review:unopenableSegment', section);
            return false ;
        },
    });
})(Review, window);

if ( Review.enabled() ) {
    $('html').on('open', 'section', function() {
//        console.log('new? ', $(this).hasClass('status-new'));
//        console.log('draft? ', $(this).hasClass('status-draft'));
        if($(this).hasClass('opened')) {
//            console.log('OPEN SEGMENT');
//            console.log($(this).find('.tab-switcher-review').length);
            $(this).find('.tab-switcher-review').click();
        }
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
//        $('#statistics .statistics-core').append('<li id="stat-quality">Overall quality: <span class="quality">Fail</span> <a href="#" class="details">(Details)</a></li>');
//        UI.createStatQualityPanel();
//        UI.populateStatQualityPanel(config.stat_quality);
    }).on('buttonsCreation', 'section', function() {
        var div = $('<ul>' + UI.segmentButtons + '</ul>');

        div.find('.translated').text('APPROVED').removeClass('translated').addClass('approved');
        div.find('.next-untranslated').parent().remove();

        UI.segmentButtons = div.html();
    }).on('footerCreation', 'section', function() {
        var div = $('<div>' + UI.footerHTML + '</div>');
        div.find('.submenu').append('<li class="active tab-switcher tab-switcher-review" id="' + $(this).attr('id') + '-review"><a tabindex="-1" href="#">Revise</a></li>');
        div.append('<div class="tab sub-editor review" id="segment-' + UI.currentSegmentId + '-review">' + $('#tpl-review-tab').html() + '</div>');

        /*
               setTimeout(function() {// fixes a bug in setting defaults in radio buttons
                   UI.currentSegment.find('.sub-editor.review .error-type input[value=0]').click();
                   UI.trackChanges(UI.editarea);
               }, 100);
        */
        UI.footerHTML = div.html();
 /*
        if(UI.body.hasClass('hideMatches')) {
            UI.currentSegment.find('.tab-switcher.active').removeClass('active');
            UI.currentSegment.find('.tab-switcher-review').addClass('active');
        } else {
            UI.currentSegment.find('.tab-switcher-review').click();
        }
*/
        UI.currentSegment.find('.tab-switcher-review').click();

    }).on('click', '.editor .tab-switcher-review', function(e) {
        e.preventDefault();

        $('.editor .submenu .active').removeClass('active');
        $(this).addClass('active');
//        console.log($('.editor .sub-editor'));
        $('.editor .sub-editor.open').removeClass('open');
        if($(this).hasClass('untouched')) {
            $(this).removeClass('untouched');
            if(!UI.body.hasClass('hideMatches')) {
                $('.editor .sub-editor.review').addClass('open');
            }
        } else {
            $('.editor .sub-editor.review').addClass('open');
        }
/*
        $('.editor .sub-editor').hide();
        $('.editor .sub-editor.review').show();
*/
    }).on('input', '.editor .editarea', function() {
        UI.trackChanges(this);
    }).on('afterFormatSelection', '.editor .editarea', function() {
        UI.trackChanges(this);
    }).on('click', '.editor .outersource .copy', function(e) {
        UI.trackChanges(UI.editarea);
    }).on('click', 'a.approved', function(e) {
        // the event click: 'A.APPROVED' i need to specify the tag a and not only the class
        // because of the event is triggered even on download button
        e.preventDefault();
        UI.tempDisablingReadonlyAlert = true;
        UI.hideEditToolbar();
        UI.currentSegment.removeClass('modified');

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
//            console.log('a: ', UI.currentSegmentId);
            UI.changeStatus(this, 'approved', 0);
            sid = UI.currentSegmentId;
            err = $('.sub-editor.review .error-type');
            err_typing = $(err).find('input[name=t1]:checked').val();
            err_translation = $(err).find('input[name=t2]:checked').val();
            err_terminology = $(err).find('input[name=t3]:checked').val();
            err_language = $(err).find('input[name=t4]:checked').val();
            err_style = $(err).find('input[name=t5]:checked').val();
//            console.log('UI.nextUntranslatedSegmentIdByServer: ', UI.nextUntranslatedSegmentIdByServer);
            UI.openNextTranslated();
            // temp fix
/*
            setTimeout(function() {
                UI.tempDisablingReadonlyAlert = false;
            }, 3000);
*/
//            console.log(UI.nextUntranslatedSegmentIdByServer);
//            UI.gotoNextSegment();

//            APP.alert('This will save the translation in the new db field.<br />Feature under construction');

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

            UI.setRevision( data );

        }
//        if(!((UI.currentSegment.find('.sub-editor.review .error-type input[value=1]').is(':checked'))||(UI.currentSegment.find('.sub-editor.review .error-type input[value=2]').is(':checked')))) console.log('sono tutti none');
    }).on('click', '.sub-editor.review .error-type input[type=radio]', function(e) {
        $('.sub-editor.review .error-type').removeClass('error');
/*
    }).on('click', '#stat-quality .details', function(e) {
        e.preventDefault();
//        UI.openStatQualityPanel();
    }).on('click', '.popup-stat-quality h1 .btn-ok, .outer-stat-quality', function(e) {
        e.preventDefault();
        $( ".popup-stat-quality").removeClass('open').hide("slide", { direction: "right" }, 400);
        $(".outer-stat-quality").hide();
        $('body').removeClass('side-popup');
*/
    }).on('setCurrentSegment_success', function(e, d, id_segment) {

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
        xEditarea = $('#segment-' + id_segment + '-editarea');
        xSegment = $('#segment-' + id_segment);
        if(d.original == '') d.original = xEditarea.text();
        if(!xSegment.find('.original-translation').length) xEditarea.after('<div class="original-translation" style="display: none">' + d.original + '</div>');
        UI.setReviewErrorData(d.error_data);
        UI.trackChanges(xEditarea);
    });

    $.extend(UI, {

        setRevision: function( data ){

            APP.doRequest({
//                data: reqData,

                data: data,

//                context: [reqArguments, segment, status],
                error: function() {
                    //UI.failedConnection( this[0], 'setRevision' );
                    UI.failedConnection( data, 'setRevision' );
                },
                success: function(d) {
//                    console.log('d: ', d);
                    $('#quality-report').attr('data-vote', d.data.overall_quality_class);
                    // temp
//                    d.stat_quality = config.stat_quality;
//                    d.stat_quality[0].found = 2;
                    //end temp
//                    UI.populateStatQualityPanel(d.stat_quality);
                }
            });
        },
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
            var diff = UI.dmp.diff_main(UI.currentSegment.find('.original-translation').text()
                    .replace( config.lfPlaceholderRegex, "\n" )
                    .replace( config.crPlaceholderRegex, "\r" )
                    .replace( config.crlfPlaceholderRegex, "\r\n" )
                    .replace( config.tabPlaceholderRegex, "\t" )
                    //.replace( config.tabPlaceholderRegex, String.fromCharCode( parseInt( 0x21e5, 10 ) ) )
                    .replace( config.nbspPlaceholderRegex, String.fromCharCode( parseInt( 0xA0, 10 ) ) ),
                $(editarea).text().replace(/(<\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?>)/gi,""));

            UI.dmp.diff_cleanupSemantic( diff ) ;

            diffTxt = '';
            $.each(diff, function (index) {

                if(this[0] == -1) {
                    var rootElem = $( document.createElement( 'div' ) );
                    var newElem = $.parseHTML( '<span class="deleted"/>' );
                    $( newElem ).text( this[1] );
                    rootElem.append( newElem );
                    diffTxt += $( rootElem ).html();
                } else if(this[0] == 1) {
                    var rootElem = $( document.createElement( 'div' ) );
                    var newElem = $.parseHTML( '<span class="added"/>' );
                    $( newElem ).text( this[1] );
                    rootElem.append( newElem );
                    diffTxt += $( rootElem ).html();
                } else {
                    diffTxt += this[1];
                }
                $('.editor .sub-editor.review .track-changes p').html(diffTxt);
            });
        },
/*
        createStatQualityPanel: function () {
            UI.body.append('<div id="popup-stat-quality">' + $('#tpl-review-stat-quality').html() + '</div>');
        },
        populateStatQualityPanel: function (d) { // no more used
            tbody = $('#popup-stat-quality .slide-panel-body tbody');
            tbody.empty();
            $.each(d, function (index) {
                $(tbody).append('<tr data-vote="' + this.vote.trim() + '"><td>' + this.type + '</td><td>' + this.allowed + '</td><td>' + this.found + '</td><td>' + this.vote + '</td></tr>')
            });
//            UI.body.append('<div id="popup-stat-quality">' + $('#tpl-review-stat-quality').html() + '</div>');
        },
        openStatQualityPanel: function() { // no more used
            $('body').addClass('side-popup');
            $(".popup-stat-quality").addClass('open').show("slide", { direction: "right" }, 400);
//            $("#SnapABug_Button").hide();
            $(".outer-stat-quality").show();
//            $.cookie('tmpanel-open', 1, { path: '/' });
        },
*/
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

        },
/*
        gotoNextUntranslated: function () {
            UI.nextUntranslatedSegmentId = UI.nextUntranslatedSegmentIdByServer;
            UI.nextSegmentId = UI.nextUntranslatedSegmentIdByServer;
            //console.log('nextUntranslatedSegmentIdByServer: ', nextUntranslatedSegmentIdByServer);
            if (UI.segmentIsLoaded(UI.nextUntranslatedSegmentIdByServer)) {
//                console.log('b: ', UI.currentSegmentId);
                UI.gotoSegment(UI.nextUntranslatedSegmentIdByServer);
//                console.log('c: ', UI.currentSegmentId);

            } else {
                UI.reloadWarning();
            }

        },
*/
/*
        closeNotYetTranslated: function () {
            return false;
        },
*/
        renderAfterConfirm: function (nextId) {
            this.render({
                firstLoad: false,
                segmentToOpen: nextId
            });
        },

        openNextTranslated: function (sid) {
            console.log('openNextTranslated');
            sid = sid || UI.currentSegmentId;
            el = $('#segment-' + sid);
//            console.log(el.nextAll('.status-translated, .status-approved'));

            var translatedList = [];
            var approvedList = [];
// console.log('QUANTI? ', el.nextAll('.status-translated, .status-approved').length);
            // find in current UI

            if(el.nextAll('.status-translated').length) { // find in next segments in the current file
                translatedList = el.nextAll('.status-translated');
//                approvedList   = el.nextAll('.status-approved');
                console.log('translatedList: ', translatedList);
//                console.log('approvedList: ', approvedList);
                if( translatedList.length ) {
                    translatedList.first().find('.editarea').click();
                } else {
//                    approvedList.first().find('.editarea').click();
                    // chiudi segmento: BELLA, HO FINITO!

//                    UI.reloadWarning();
//                    approvedList.first().find('.editarea').click();
                }

            } else {
                file = el.parents('article');
                file.nextAll(':has(section.status-translated)').each(function () { // find in next segments in the next files

                    var translatedList = $(this).find('.status-translated');
//                    var approvedList   = $(this).find('.status-approved');

                    if( translatedList.length ) {
                        translatedList.first().find('.editarea').click();
                    } else {
                        UI.reloadWarning();
//                        approvedList.first().find('.editarea').click();
                    }

                    return false;

                });
                // else
                if($('section.status-translated').length) { // find from the beginning of the currently loaded segments
console.log('AAA');
                    translatedList = $('section.status-translated');
//                    approvedList   = $('section.status-approved');

                    if( translatedList.length ) {
                        if((translatedList.first().is(UI.currentSegment))) {
                            UI.scrollSegment(translatedList.first());
                        } else {
                            translatedList.first().find('.editarea').click();
                        }
/*
                    } else {
                        if((approvedList.first().is(UI.currentSegment))) {
                            UI.scrollSegment(approvedList.first());
                        } else {
                            approvedList.first().find('.editarea').click();
                        }
*/
                    }

                } else { // find in not loaded segments
                    console.log('ask for getNextReviseSegment');
//                    console.log('got to ask to server next translated segment id, and then reload to that segment');
                    APP.doRequest({
                        data: {
                            action: 'getNextReviseSegment',
                            id_job: config.job_id,
                            password: config.password,
                            id_segment: sid
                        },
                        error: function() {
                        },
                        success: function(d) {
                            if( d.nextId == null ) return false;
                            if($(".modal[data-type='confirm']").length) {
                                $(window).on('statusChanged', function(e) {
                                    UI.renderAfterConfirm(d.nextId);
                                });
                            } else {
                                UI.renderAfterConfirm(d.nextId);
                            }

                        }
                    });
                }
            }
        }

    })
}
