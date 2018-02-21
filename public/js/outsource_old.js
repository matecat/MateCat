$.extend(UI, {

    // Coupon : calledd now from the outsourceVendor Component
    populateOutsourceForm : function() {
    },
    showPopupDetails: '1',
    changeRates: [],
	outsourceInit: function() {
        this.removeEvents();

        // add/remove revision service to current job
        $( "input[name='revision']" ).click(function() {
            $(this).parent().toggleClass('noopacity');
            OutsourceActions.outsourceCloseTranslatorInfo();
            var fullTranslateUrl = $(".onyourown a.uploadbtn:not(.showprices)").attr("href");
            // if ($(".translate[href='" + fullTranslateUrl.substr(fullTranslateUrl.indexOf("/translate/")) + "']").length > 0) {
            //     $(".translate[href='" + fullTranslateUrl.substr(fullTranslateUrl.indexOf("/translate/")) + "']").trigger( "click" );
            // } else {
                UI.restartOutsourceModal()
            // }

            $('.revision_heading').toggleClass('hide');
        });

        $("body").on('click', '.modal .x-popup', function(e) {
            e.preventDefault();
            $( "body").removeClass( "showingOutsourceTo" );
            APP.ModalWindow.onCloseModal();
        });

        // close and reset the popup to its default state
        $('.modal.outsource .x-popup').click( function() {
            resetOutsourcePopup( true );
        });

        // change the currency prices are shown in
        $( "#changecurrency" ).change( function(){
            var currencyFrom = $( ".displayprice").attr( "data-currency" );
            var currencyTo  = $( "#changecurrency option:selected" ).val();
            changeCurrency( $( ".displayprice").attr( "data-rawprice" ), currencyFrom, currencyTo, ".euro", ".displayprice", ".price_p_word" );
            changeCurrency( $( ".revision_price").attr( "data-rawprice" ), currencyFrom, currencyTo, ".revision_currency", ".revision_price", "" );
        });

        // change the timezone deliveries are shown in
        $( "#changeTimezone" ).change( function(){
            var timezoneFrom = $( "span.time").attr( "data-timezone" );
            var timezoneTo = $( "#changeTimezone option:selected" ).val();
            changeTimezone( $( "span.time").attr( "data-rawtime" ), timezoneFrom, timezoneTo, "span.time" );
            changeTimezone( $( "span.revision_delivery").attr( "data-rawtime" ), timezoneFrom, timezoneTo, "span.revision_delivery" );
            updateTimezonesDescriptions( timezoneTo );
        });

		$(".outsource.modal .continuebtn").on('click', function(e) {
			e.preventDefault();

            if( $( this ).hasClass( 'disabled' ) ) {
                return;
            }

            updateCartParameters();

            $('#continueForm input[name=url_ok]').attr('value', UI.url_ok);
            $('#continueForm input[name=url_ko]').attr('value', UI.url_ko);
            $('#continueForm input[name=confirm_urls]').attr('value', UI.confirm_urls);
            $('#continueForm input[name=data_key]').attr('value', UI.data_key);

            UI.populateOutsourceForm();

            //IMPORTANT post out the quotes
			$('#continueForm input[name=quoteData]').attr('value', JSON.stringify( UI.quoteResponse ) );
			$('#continueForm').submit();
            $('#continueForm input[name=quoteData]').attr('value', '' );
            APP.closePopup();

		});
        //
        // $('.modal.outsource input.out-email').on('keyup', function () {
        //     _.debounce(function() {
        //         // UI.checkInputEmailInput();
        //         UI.checkSendToTranslatorButton();
        //     }, 300)();
        // });

        $(".outsource.modal .send-to-translator-btn").on('click', function(e) {
            e.preventDefault();
            UI.sendJobToTranslator();
        });

        $("#open-translator").on('click', function () {
            $("#open-translator").addClass('hide');
            $('.onyourown').addClass('opened-send-translator');
            $('.send-to-translator').removeClass('hide');
        });

        fetchChangeRates();
	},
    removeEvents: function () {
        $( "input[name='revision']" ).off("click");
        $("body .modal .x-popup").off('click');
        $('.modal.outsource .x-popup').off('click');
        $( "#changecurrency" ).off('change');
        $( "#changeTimezone" ).off('change');
        $( ".showprices").off('click');
        $(".outsource.modal .continuebtn").off('click');
        $('.modal.outsource input.out-email').off('keyup');
        $(".outsource.modal .send-to-translator-btn").off('click');
        $("#open-translator").off('click');
    },

    restartOutsourceModal: function () {
        if(config.enable_outsource) {
            resetOutsourcePopup( false );
            renderQuoteFromManage(this.currentOutsourceProject.id, this.currentOutsourceProject.password, this.currentOutsourceJob.id, this.currentOutsourceJob.password);
            $('.outsource.modal').show();
        }
    },

    // checkSendToTranslatorButton: function () {
    //     var $email = $('.modal.outsource input.out-email');
    //     var email = $email.val();
    //     var date = $('.outsource .out-date').val();
    //
    //     if ($email.hasClass('error') ) {
    //         $email.removeClass('error');
    //         $('.modal.outsource .validation-error.email-translator-error').hide();
    //     }
    //
    //     if (email.length > 0 && date.length > 0 ) {
    //         $('.send-to-translator-btn').removeClass('disabled');
    //         return true;
    //     } else {
    //         $('.send-to-translator-btn').addClass('disabled');
    //         return false;
    //     }
    // },

    // checkInputEmailInput: function () {
    //     var email = $('.modal.outsource input.out-email').val();
    //     if (!APP.checkEmail(email)) {
    //         $('.modal.outsource input.out-email').addClass('error');
    //         $('.modal.outsource .validation-error.email-translator-error').show();
    //         return false;
    //     } else {
    //         $('.modal.outsource input.out-email').removeClass('error');
    //         $('.modal.outsource .validation-error.email-translator-error').hide();
    //         return true;
    //     }
    // },

    sendJobToTranslator: function (email, date, timezone, job, project) {
        UI.sendTranslatorRequest(email, date, timezone, job).done(function (data) {
            APP.ModalWindow.onCloseModal();
            if (data.job) {
                UI.checkShareToTranslatorResponse(data, email, date, job, project);
            } else {
                UI.showShareTranslatorError();
            }
        }).fail(function () {
            UI.showShareTranslatorError();
        });

    },

    sendTranslatorRequest: function (email, date, timezone, job) {
        var data = {
            email: email,
            delivery_date: Math.round(date/1000),
            timezone: timezone
        };
        return $.ajax({
            async: true,
            data: data,
            type: "POST",
            url : "/api/v2/jobs/" + job.id +"/" + job.password + "/translator"
        });
    },

    getOutsourceQuote: function(idProject, password, jid, jpassword, fixedDelivery, typeOfService, timezone ) {

        var data = {
            action: 'outsourceTo',
                pid: idProject,
                currency: 'EUR',
                ppassword: password,
                fixedDelivery: fixedDelivery,
                typeOfService: typeOfService,
                timezone: timezone,
                jobs: [
                {
                    jid: jid,
                    jpassword: jpassword
                }
            ]
        };

        return $.ajax({
            data: data,
            type: "POST",
            url : "/?action=outsourceTo"
        });
    },

    getOutsourceQuoteFromManage: function(idProject, password, jid, jpassword, fixedDelivery, typeOfService ) {

        return APP.doRequest({
            data: {
                action: 'outsourceTo',
                pid: idProject,
                ppassword: password,
                fixedDelivery: fixedDelivery,
                typeOfService: typeOfService,
                jobs: [
                    {
                        jid: jid,
                        jpassword: jpassword
                    }
                ]
            },
            success: function (d) {
                if (typeof callback == "function")
                    callback(d);
            }
        });

    },

    checkShareToTranslatorResponse: function (response, mail, date, job, project) {
        var message = '';
        if (job.translator) {
            var newDate = new Date(date);
            var oldDate = new Date(job.translator.delivery_date);
            if (oldDate.getTime() !== newDate.getTime()) {
                message = this.shareToTranslatorDateChangeNotification(mail, oldDate, newDate);
            } else if (job.translator !== mail) {
                message = this.shareToTranslatorMailChangeNotification(mail);
            } else {
                message = this.shareToTranslatorNotification(mail, job);
            }
        } else {
            message = this.shareToTranslatorNotification(mail, job);
        }
        var notification = {
            title: message.title,
            text: message.text,
            type: 'success',
            position: 'tc',
            allowHtml: true,
            timer: 10000
        };
        var boxUndo = APP.addNotification(notification);
        ManageActions.changeJobPasswordFromOutsource(project.id ,job.id, job.password, response.job.password);
        ManageActions.assignTranslator(project.id ,job.id, job.password, response.job.translator);
    },

    shareToTranslatorNotification : function (mail, job) {
        return message = {
            title: 'Job sent',
            text: '<div style="margin-top: 16px;">To: <a href="mailto:' + mail + '">' + mail + '</a> ' +
            '<div class="job-reference" style="display: inline-block; width: 100%; margin-top: 10px;"> ' +
            '<div class style="display: inline-block; font-size: 14px; color: grey;">(' + job.id +')</div> ' +
            '<div class="source-target languages-tooltip" style="display: inline-block; font-weight: 700;"> ' +
            '<div class="source-box" style="display: inherit;">' + job.sourceTxt + '</div> ' +
            '<div class="in-to" style="top: 3px; display: inherit; position: relative;"> <i class="icon-chevron-right icon"></i> </div> ' +
            '<div class="target-box" style="display: inherit;">' + job.targetTxt + '</div> </div> </div></div>'
        } ;

    },

    shareToTranslatorDateChangeNotification : function (email, oldDate, newDate) {
        oldDate = $.format.date(oldDate, "yyyy-MM-d hh:mm a");
        oldDate =  APP.getGMTDate(oldDate);
        newDate = $.format.date(newDate, "yyyy-MM-d hh:mm a");
        newDate =  APP.getGMTDate(newDate);
        return message = {
            title: 'Job delivery update',
            text: '<div style="margin-top: 16px;"><div class="job-reference" style="display: inline-block; width: 100%;"> To: ' +
            '<div class="job-delivery" title="Delivery date" style="display: inline-block; margin-bottom: 10px; font-weight: 700; margin-right: 10px;"> ' +
                '<div class="outsource-day-text" style="display: inline-block; margin-right: 3px;">'+ newDate.day +'</div> ' +
                '<div class="outsource-month-text" style="display: inline-block; margin-right: 5px;">'+ newDate.month +'</div> ' +
                '<div class="outsource-time-text" style="display: inline-block;">'+ newDate.time +'</div> ' +
                '<div class="outsource-gmt-text" style="display: inline-block; font-weight: 100;color: grey;">('+ newDate.gmt +')</div> ' +
            '</div> <div class="job-delivery not-used" title="Delivery date" style="display: inline-block; margin-bottom: 10px; font-weight: 700; text-decoration: line-through; position: relative;"> ' +
                '<div class="outsource-day-text" style="display: inline-block; margin-right: 3px;">'+ oldDate.day +'</div> ' +
                '<div class="outsource-month-text" style="display: inline-block; margin-right: 5px;">'+ oldDate.month +'</div> ' +
                '<div class="outsource-time-text" style="display: inline-block;">'+ oldDate.time +'</div> ' +
                '<div class="outsource-gmt-text" style="display: inline-block; font-weight: 100; color: grey;">('+ oldDate.gmt +')</div> ' +
                '<div class="old" style="width: 100%; height: 1px; border-top: 1px solid black; top: -10px; position: relative;"></div> </div> ' +
            '</div>Translator: <a href="mailto:'+email+'">'+email+'</a> </div></div>'
        } ;

    },

    shareToTranslatorMailChangeNotification : function (mail) {
        return message = {
            title: 'Job sent with <div class="green-label" style="display: inline; background-color: #5ea400; color: white; padding: 2px 5px;">new password </div>',
            text: '<div style="margin-top: 16px;">To: <a href="mailto:' + mail + '">' + mail + '</a> ' +
            '<div class="job-reference" style="display: inline-block; width: 100%; margin-top: 10px;"> ' +
            '<div class style="display: inline-block; font-size: 14px; color: grey;">(' + UI.currentOutsourceJob.id +')</div> ' +
            '<div class="source-target languages-tooltip" style="display: inline-block; font-weight: 700;"> ' +
            '<div class="source-box" style="display: inherit;">' + UI.currentOutsourceJob.sourceTxt + '</div> ' +
            '<div class="in-to" style="top: 3px; display: inherit; position: relative;"> <i class="icon-chevron-right icon"></i> </div> ' +
            '<div class="target-box" style="display: inherit;">' + UI.currentOutsourceJob.targetTxt + '</div> </div> </div></div>'
        } ;

    },
    showShareTranslatorError: function () {
        APP.ModalWindow.onCloseModal();
        var notification = {
            title: 'Problems sending the job',
            text: 'Please try later or contact <a href="mailto:support@matecat.com">support@matecat.com</a>',
            type: 'error',
            position: 'tc',
            allowHtml: true,
            timer: 10000
        };
        APP.addNotification(notification);
    }

});


function renderQuote( clickedButton ) {

    getOutsourceQuote( clickedButton, function( quoteData ){
        UI.quoteResponse = quoteData.data[0];
        var chunk = quoteData.data[0][0];

        UI.url_ok = quoteData.return_url.url_ok;
        UI.url_ko = quoteData.return_url.url_ko;
        UI.confirm_urls = quoteData.return_url.confirm_urls;
        UI.data_key = chunk.id;

        // a generic error
        if( chunk.quote_result != 1 ){
            renderGenericErrorQuote();
            return false;
        }

        // job already outsourced
        if( chunk.outsourced == 1 ) {
            renderOutsourcedQuote( chunk );
            return false;
        }

        // delivery date too strict
        if( chunk.quote_available != 1 ) {
            renderNotAvailableQuote();
            return false;
        }

        renderNormalQuote( chunk );

        $(document).trigger('outsource-rendered', { quote_data : UI.quoteResponse } );
    });
}

function renderQuoteFromManage( idProject, password, jid, jpassword) {

    OutsourceActions.getOutsourceQuote();

}



// function getOutsourceQuote( clickedButton, callback ) {
//     var row = clickedButton.parents('.tablestats');
//
//     APP.doRequest({
//         data: {
//             action: 'outsourceTo',
//             pid: config.id_project,
//             ppassword: config.password,
//             fixedDelivery: $( "#forceDeliveryChosenDate" ).text(),
//             typeOfService: $( "input[name='revision']" ).is(":checked") ? "premium" : "professional",
//             jobs: [
//                 {
//                     jid: row.attr('data-jid'),
//                     jpassword: row.attr('data-pwd')
//                 }
//             ]
//         },
//         context: clickedButton.parents('.totaltable').find('.languages .splitnum').text(),
//         success: function(d) {
//             if( typeof callback == "function" )
//                 callback( d );
//         }
//     });
// }


function renderGenericErrorQuote() {
    $('.modal.outsource').removeClass('loading');
    $('#forceDeliveryContainer').css('top','465px');
    $('.ErrorMsgQuoteError').removeClass('hide');
    $('.addrevision, .delivery_details span.time, .delivery_label,.euro,.displayprice,.displaypriceperword, .delivery_details span.zone2, .outsource.modal .continuebtn, .outsource #changeTimezone,.outsource #changecurrency,.paymentinfo,.modal.outsource .contact_box, .modal.outsource .more, .needitfaster').addClass('hide');
}


function renderOutsourcedQuote( chunk ) {
    $('.modal.outsource').removeClass('loading');
    $(".outsourceto").addClass("outsourced");
    $('.needitfaster,#changecurrency,#changeTimezone,.show_translator,.addrevision,.outsource.modal .continuebtn').addClass('hide');
    $('.outsource.modal .tprice').append('<a class="checkstatus standardbtn" href="'+chunk.link_to_status+'" target="_blank">View status</a>').removeClass('hide');
    $('.outsourced .heading').append('<span class="outsource_notify"><span class="icon-check"></span> Outsourced</span>');

    if (chunk.typeOfService == "premium") {
        $('.revision_heading').removeClass('hide');
    } else {
        $('.revision_heading').addClass('hide');
    }

    renderLocalizationInfos( chunk.price, chunk.delivery );
    $( 'span.zone2').html( $( '#changeTimezone option:selected').attr( "data-description-long" ) );

}


function renderLocalizationInfos( price, delivery, revision_price, revision_delivery ) {
    // if the customer has a timezone in the cookie, then use it
    // otherwise attemp to guess it from his browser infos
    var timezoneToShow = APP.readCookie( "matecat_timezone" );
    if ( timezoneToShow == "" ) {
        timezoneToShow = -1 * ( new Date().getTimezoneOffset() / 60 );
    }

    // update the timezone (both the displayed and the stored ones)
    changeTimezone(delivery, -1 * ( new Date().getTimezoneOffset() / 60 ), timezoneToShow, "span.time");
    if( revision_delivery ) {
        changeTimezone(revision_delivery, -1 * ( new Date().getTimezoneOffset() / 60 ), timezoneToShow, "span.revision_delivery");
    }
    updateTimezonesDescriptions( timezoneToShow );


    // if the customer has a currency in the cookie, then use it
    // otherwise use the default one
    var currToShow = APP.readCookie( "matecat_currency" );
    if ( currToShow == "" ) {
        currToShow = "EUR";
    }

    // update the currency (both the displayed and the stored ones)
    changeCurrency( price, "EUR", currToShow, ".euro", ".displayprice", ".price_p_word");
    if( revision_price ) {
        changeCurrency(revision_price, "EUR", currToShow, ".revision_currency", ".revision_price", "");
    }

    $( "#changecurrency option[value='" + currToShow + "']").attr( "selected", "selected" );
}


function renderNotAvailableQuote() {
    $('.modal.outsource').removeClass('loading');
    $('.needitfaster').html('Change delivery date');
    $('.outsource.modal .forceDeliveryButtonOk').addClass('disabled');
    $('#forceDeliveryContainer #delivery_not_available, .ErrorMsgquoteNotAvailable').removeClass('hide');
    $('.guaranteed_by .more, .delivery_details span.time, .delivery_label,.euro,.displayprice,.displaypriceperword, .delivery_details span.zone2, .revision_delivery, .revision_price_box,#delivery_before_time').addClass('hide');
}


function renderNormalQuote( chunk ) {
    // if the customer has a timezone in the cookie, then use it
    // otherwise attempt to guess it from his browser infos
    var isRevisionChecked = $( "input[name='revision']" ).is( ":checked" );
    var deliveryToShow = ( isRevisionChecked ) ?  chunk.r_delivery : chunk.delivery;
    var priceToShow = ( isRevisionChecked ) ? parseFloat( chunk.r_price ) + parseFloat( chunk.price ) : chunk.price;

    renderLocalizationInfos( priceToShow, deliveryToShow, chunk.r_price, chunk.r_delivery );

    if( new Date( deliveryToShow ).getTime() < $( "#forceDeliveryChosenDate" ).text() ) {
        $( ".delivery_container > .delivery").addClass( "faster" );
        $('#delivery_before_time').removeClass('hide');
        $("#delivery_manual_error").addClass( "hide" );
        $('.modal.outsource .tooltip').removeClass('hide');

    } else {
        $( ".delivery_container > .delivery").removeClass( "faster" );
        $('#delivery_before_time').addClass('hide');
        $('.modal.outsource .tooltip').addClass('hide');
    }


    // no info available about translator
    if( chunk.show_translator_data != 1 ) {
        $('.outsourceto').addClass("translatorNotAvailable");
        // $('.outsource.modal .minus').hide();
        $('.trustbox2').removeClass('hide');
        $('.translator_bio,.outsource.modal .more,.trustbox1, .translator_not_found,.translator_not_found, .trust_text p:first-child').addClass('hide');
        return false;
    }


    var subjectsString = "";
    if (chunk.t_chosen_subject.length > 0 && chunk.t_subjects.length > 0) {
        subjectsString = "<strong>" + chunk.t_chosen_subject + "</strong>, " + chunk.t_subjects;
    } else if (chunk.t_chosen_subject.length > 0) {
        subjectsString = "<strong>" + chunk.t_chosen_subject + "</strong>";
    } else {
        subjectsString = chunk.t_subjects;
    }

    $(".translator_name > strong").text(chunk.t_name);
    $(".experience").text(chunk.t_experience_years);
    $(".subjects").html(subjectsString);
    $(".translated_words").html(chunk.t_words_total.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,"));

    var voteToShow = ( isRevisionChecked ) ? chunk.r_vote : chunk.t_vote;

    if( chunk.show_revisor_data != 1 ) {
        $(".outsourceto").addClass("revisorNotAvailable");
        voteToShow = chunk.t_vote;
    }

    $(".score_number").text(parseInt(voteToShow) + "%");
}


function changeCurrency( amount, currencyFrom, currencyTo, elementToUpdateSymbol, elementToUpdateValue, elementToUpdatePPW ) {
    fetchChangeRates( function() {
        $('.tprice').addClass('blink');

        var changeRates = $.parseJSON( UI.changeRates );
        var newPrice = amount * changeRates[ currencyTo ] / changeRates[ currencyFrom ];

        $( elementToUpdateValue ).attr( "data-rawprice", newPrice );
        $( elementToUpdateValue ).attr( "data-currency", currencyTo );
        $( elementToUpdateSymbol ).attr( "data-currency", currencyTo );

        $( elementToUpdateSymbol ).text( $( "#changecurrency" ).find( "option[value='" + currencyTo + "']" ).attr( "data-symbol" ) );
        $( elementToUpdateValue ).text( parseFloat( newPrice ).toFixed( 2).replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,") );

        if( elementToUpdatePPW.length > 0 ) {
            var numWords = parseFloat($(".title-words").text().replace(",", ""));

            if(numWords == 0){
                numWords = 1;
            }

            $(elementToUpdatePPW).text(( parseFloat(newPrice) / numWords ).toFixed(3).replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,"));
        }

        $('.outsource.modal .continuebtn').removeClass('disabled');
        $('.modal.outsource').removeClass('loading');
        $(".showpricesloading").addClass("hide");
        $(".showprices").removeClass("hide"); $(".showprices").show();

        APP.setCookie( "matecat_currency", currencyTo );
    });
}


function fetchChangeRates( callback ) {
    if( UI.changeRates != "" ) {
        if( typeof callback == "function" ) callback();
        return;
    }

    var changeRates = APP.readCookie( "matecat_changeRates" );
    if( changeRates != "" && changeRates!="null") {
        UI.changeRates = changeRates;
        if( typeof callback == "function" ) callback();
        return;
    }

    APP.doRequest({
        data: {
            action: 'fetchChangeRates'
        },
        success: function(d) {
            var now = new Date();
            APP.setCookie( "matecat_changeRates", d.data, new Date( now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59 ) );
            UI.changeRates = d.data;
            if( typeof callback == "function" ) callback();
        }
    });
}


function changeTimezone( date, timezoneFrom, timezoneTo, elementToUpdate ){
    var dd = new Date( date.replace(/-/g, "/") );
    dd.setMinutes( dd.getMinutes() + (timezoneTo - timezoneFrom) * 60 );
    $( elementToUpdate ).text( $.format.date(dd, "d MMMM") + ' at ' + $.format.date(dd, "hh") + ":" + $.format.date(dd, "mm") + " " + $.format.date(dd, "a") );

    $( elementToUpdate ).attr("data-timezone", timezoneTo);
    $( elementToUpdate ).attr("data-rawtime", dd.toUTCString());

    APP.setCookie( "matecat_timezone", timezoneTo );
}


function updateTimezonesDescriptions( selectedTimezone ) {
    $( "#changeTimezone" ).find( "option").each( function() {
        $( this ).text( $( this ).attr( "data-description-long" ) );
    });

    var selectedElement = $( "#changeTimezone" ).find( "option[value='" + selectedTimezone + "']");
    selectedElement.text( selectedElement.attr( "data-description-short" ) );
}

function updateCartParameters() {
    var linkPieces = $( "a.uploadbtn.in-popup").attr( "href").split( "/" );
    var jobData = linkPieces[ linkPieces.length - 1].split( "-" );

    APP.doRequest({
        data: {
            action: 'outsourceTo',
            pid: config.id_project,
            ppassword: config.password,
            fixedDelivery: $( "#forceDeliveryChosenDate" ).text(),
            typeOfService: $( "input[name='revision']" ).is(":checked") ? "premium" : "professional",
            jobs: [
                {
                    jid: jobData[0],
                    jpassword: jobData[1]
                }
            ]
        }
    });

}


function resetOutsourcePopup( resetHard ) {
    $('.outsourceto').attr( "class", "outsourceto" );
    $('.needitfaster').html('Need it faster?');
    $('.delivery_details span.zone2').html('');
    $('.trustbox2').attr( "class", "trustbox2" );
    $('.trustbox1').attr( "class", "trustbox1" );
    $('.translator_bio').attr( "class", "translator_bio" );
    $('.translator_info_box').attr( "class", "translator_info_box" );
    $('.revision_delivery').attr( "class", "revision_delivery" );
    $('.revision_price_box').attr( "class", "revision_price_box" );
    $('.guaranteed_by.expanded').attr( "class", "guaranteed_by" );
    $('.tprice.compress').attr( "class", "tprice" );
    $( ".show_translator.more").attr( "class", "show_translator more" );
    $( ".hide_translator.more").attr( "class", "hide_translator more" );
    $('.popup-box.pricebox.compress').attr( "class", "popup-box pricebox" );
    $('.modal.outsource, .outsource.modal .continuebtn').addClass('loading');
    // $('.delivery').appendTo(".delivery_container").attr("class","delivery");
    $('.outsource.modal .continuebtn').addClass('disabled');
    $('.tprice').removeClass('blink');
    $('.modal.outsource .continuebtn, .modal.outsource .contact_box,.paymentinfo,.outsource #changeTimezone,.outsource #changecurrency,.addrevision, .delivery_details span.time, .delivery_label,.euro,.displayprice,.displaypriceperword, .delivery_details span.zone2, .needitfaster, .showpricesloading').removeClass('hide');
    $('.ErrorMsg,.modal.outsource .tooltip, .outsource_notify, .delivery_before_time, .checkstatus, #delivery_not_available, .trustbox2, .translator_info_box, .showprices').addClass('hide');
    $('#out-datepicker').addClass('hide');
    $('.modal.outsource input.out-email').removeClass('error');
    $('.modal.outsource .validation-error.email-translator-error').hide();
    $('.send-to-translator-btn').addClass('disabled');
    if( resetHard ) {
        $('.modal.outsource .displayprice').empty();
        $('.modal.outsource .delivery .time').empty();
        $('.modal.outsource .revealprices, .modal.outsource').hide();
        $('#forceDeliveryContainer').addClass("hide");
    }
}


