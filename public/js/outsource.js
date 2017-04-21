$.extend(UI, {
    populateOutsourceForm : function() {
    },
    showPopupDetails: '1',
    changeRates: [],
	outsourceInit: function() {
        this.removeEvents();
        // hide/show detailed information about the chosen translator
        // $('.show_translator, .hide_translator').click(function() {
        //     $('.show_translator, .hide_translator').toggleClass('hide');
        //     $('.guaranteed_by').toggleClass('expanded');
        //     $('.delivery,.tprice,.pricebox').toggleClass('compress');
        //     $('.trustbox2').toggleClass('hide');
        //     $('.translator_info_box').toggleClass('hide');
        //     $('#forceDeliveryContainer').addClass('hide');
        //     $('.delivery').appendTo( $( this).hasClass( "hide_translator" ) ? ".delivery_container" : ".displaypriceperword" );
        // });

        // add/remove revision service to current job
        $( "input[name='revision']" ).click(function() {
            $(this).parent().toggleClass('noopacity');
            var fullTranslateUrl = $(".onyourown a.uploadbtn:not(.showprices)").attr("href");
            if ($(".translate[href='" + fullTranslateUrl.substr(fullTranslateUrl.indexOf("/translate/")) + "']").length > 0) {
                $(".translate[href='" + fullTranslateUrl.substr(fullTranslateUrl.indexOf("/translate/")) + "']").trigger( "click" );
            } else {
                UI.restartOutsourceModal()
            }

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

        // expand a compressed popup to show price, delivery and other details
        $( ".showprices").click( function() {
            expandOutsourcePopup();
        });

        /*// trigger the process for getting and displaying an outsource quote
		$(".translate").click(function(e) {
			var linkPieces = $( this ).attr( "href" ).split( "/" );
			var jPieces = linkPieces[ linkPieces.length - 1 ].split( "-" );

			var words = $( ".tablestats[data-pwd='" + jPieces[ 1 ] + "'] .stat-payable" ).text() ;
			var sourceTxt = $( "div[data-jid='" + jPieces[ 0 ] + "'] .source_lang" ).text();
			var targetTxt = $( "div[data-jid='" + jPieces[ 0 ] + "'] .target_lang" ).text();

			$( ".title-source" ).text( sourceTxt );
			$( ".title-target" ).text( targetTxt );
			$( ".title-words" ).text( words );

            UI.currentOutsourceJob = {
                id: jPieces[ 0 ],
                password: jPieces[ 1 ],
                stats: {
                    TOTAL_FORMATTED: words
                },
                sourceTxt: sourceTxt,
                targetTxt: targetTxt
            };
            UI.currentOutsourceProject = {
                id: config.id_project,
                password: config.password,
            };

            UI.currentOutsourceUrl = $( this ).attr( "href" );

            let props = {
                project: UI.currentOutsourceProject,
                job: UI.currentOutsourceJob,
                url: UI.currentOutsourceUrl,
                fromManage: false,
                translatorOpen: false
            };
            let style = {width: '970px',maxWidth: '970px', top: '45%'};
            APP.ModalWindow.showModalComponent(OutsourceModal, props, "Translate", style);
            // if(config.enable_outsource) {
				// e.preventDefault();
            //     resetOutsourcePopup( false );
            //     $('body').addClass('showingOutsourceTo');
            //     $('.outsource.modal input.out-link').val(window.location.protocol + '//' + window.location.host + $(this).attr('href'));
            //     $('.outsource.modal .uploadbtn:not(.showprices)').attr('href', $(this).attr('href'));
            //     renderQuote( $( this ) );
            //     // $('.outsource.modal').show();
            // }
		});*/

		$(".outsource.modal").on('click', '.continuebtn', function(e) {
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
		});

        $('.modal.outsource input.out-email').on('keyup', function () {
            _.debounce(function() {
                // UI.checkInputEmailInput();
                UI.checkSendToTranslatorButton();
            }, 300)();
        });

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
    startOutSourceModal: function (project, job, url) {
        this.currentOutsourceProject = project;
        this.currentOutsourceJob = job;
        this.currentOutsourceUrl = url;

        // $( ".title-source" ).text( job.sourceTxt);
        // $( ".title-target" ).text( job.targetTxt);
        // $( ".title-words" ).text(job.stats.TOTAL_FORMATTED );
        // $( ".modal.outsource a.uploadbtn.in-popup").hide();

        // if (job.outsource) {
        //     var dd = new Date( job.outsource.delivery_date );
        //     $('.modal.outsource .out-date').val( $.format.date(dd, "d MMMM") + ' at ' + $.format.date(dd, "hh") + ":" + $.format.date(dd, "mm") + " " + $.format.date(dd, "a") );
        // } else {
        //     $('.modal.outsource .out-date').val( getChosenOutsourceDateToString() );
        //
        // }

        if(config.enable_outsource) {
            // resetOutsourcePopup( false );
            // $('body').addClass('showingOutsourceTo');
            // $('.outsource.modal input.out-link').val(window.location.protocol + '//' + window.location.host + url);

            //TODO Questo ignoro a cosa serva
            // $('.outsource.modal .uploadbtn:not(.showprices)').attr('href', url);


            // $("#open-translator").addClass('hide');
            // $('.send-to-translator').removeClass('hide');
            // $('.onyourown').addClass('opened-send-translator');
            // $('.out-link').addClass('from-manage');

            //TODO
            // $('.modal.outsource input.out-email').val("");
            // $('.modal.outsource input.out-email').removeClass("error");

            renderQuoteFromManage(project.id, project.password, job.id, job.password);
            // $('.outsource.modal').show();
        }
    },
    restartOutsourceModal: function () {
        if(config.enable_outsource) {
            resetOutsourcePopup( false );
            renderQuoteFromManage(this.currentOutsourceProject.id, this.currentOutsourceProject.password, this.currentOutsourceJob.id, this.currentOutsourceJob.password);
            $('.outsource.modal').show();
        }
    },

    checkSendToTranslatorButton: function () {
        var $email = $('.modal.outsource input.out-email');
        var email = $email.val();
        var date = $('.outsource .out-date').val();

        if ($email.hasClass('error') ) {
            $email.removeClass('error');
            $('.modal.outsource .validation-error.email-translator-error').hide();
        }

        if (email.length > 0 && date.length > 0 ) {
            $('.send-to-translator-btn').removeClass('disabled');
            return true;
        } else {
            $('.send-to-translator-btn').addClass('disabled');
            return false;
        }
    },

    checkInputEmailInput: function () {
        var email = $('.modal.outsource input.out-email').val();
        if (!APP.checkEmail(email)) {
            $('.modal.outsource input.out-email').addClass('error');
            $('.modal.outsource .validation-error.email-translator-error').show();
            return false;
        } else {
            $('.modal.outsource input.out-email').removeClass('error');
            $('.modal.outsource .validation-error.email-translator-error').hide();
            return true;
        }
    },

    sendJobToTranslator: function () {
        if (UI.checkSendToTranslatorButton() && UI.checkInputEmailInput()) {
            var email = $('.modal.outsource input.out-email').val();
            var date = getChosenOutsourceDate();
            UI.sendTranslatorRequest(email, date).done(function () {
                APP.ModalWindow.onCloseModal();
                let notification = {
                    title: 'Job sent',
                    text: '<div class="translator-notification-sent">' + UI.currentOutsourceJob.sourceTxt + ' > ' + UI.currentOutsourceJob.targetTxt + ' - ' + UI.currentOutsourceJob.stats.TOTAL_FORMATTED + ' words ' +
                    '<br/>To: <span>' + email + '</span> </div>',
                    type: 'success',
                    position: 'tc',
                    allowHtml: true,
                    timer: 10000
                };
                let boxUndo = APP.addNotification(notification);
            }).error(function () {
                APP.ModalWindow.onCloseModal();
                let notification = {
                    title: 'Problems sending the job',
                    text: 'Please try later or contact <a href="mailto:support@matecat.com">support@matecat.com</a>',
                    type: 'error',
                    position: 'tc',
                    allowHtml: true,
                    timer: 10000
                };
                let boxUndo = APP.addNotification(notification);
            });
        }
    },

    sendTranslatorRequest: function (email, date) {
        let data = {
            email: email,
            delivery_date: date
        };
        return $.ajax({
            async: true,
            data: data,
            type: "POST",
            url : "/api/v2/jobs/" + UI.currentOutsourceJob.id +"/" + UI.currentOutsourceJob.password + "/translator"
        });
    },

    getOutsourceQuoteFromManage: function(idProject, password, jid, jpassword, fixedDelivery, typeOfService, callback ) {

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

    var typeOfService = $( "input[name='revision']" ).is(":checked") ? "premium" : "professional";
    var fixedDelivery =  $( "#forceDeliveryChosenDate" ).text();

    getOutsourceQuoteFromManage( idProject, password, jid, jpassword, fixedDelivery, typeOfService, function(quoteData ){
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



function getOutsourceQuote( clickedButton, callback ) {
    var row = clickedButton.parents('.tablestats');

    APP.doRequest({
        data: {
            action: 'outsourceTo',
            pid: config.id_project,
            ppassword: config.password,
            fixedDelivery: $( "#forceDeliveryChosenDate" ).text(),
            typeOfService: $( "input[name='revision']" ).is(":checked") ? "premium" : "professional",
            jobs: [
                {
                    jid: row.attr('data-jid'),
                    jpassword: row.attr('data-pwd')
                }
            ]
        },
        context: clickedButton.parents('.totaltable').find('.languages .splitnum').text(),
        success: function(d) {
            if( typeof callback == "function" )
                callback( d );
        }
    });
}

function getOutsourceQuoteFromManage(idProject, password, jid, jpassword, fixedDelivery, typeOfService, callback ) {

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
        success: function(d) {
            if( typeof callback == "function" )
                callback( d );
        }
    });
}


function renderGenericErrorQuote() {
    $('.modal.outsource').removeClass('loading');
    $('#forceDeliveryContainer').css('top','465px');
    $('.ErrorMsgQuoteError').removeClass('hide');
    $('.addrevision, .delivery_details span.time, .delivery_label,.euro,.displayprice,.displaypriceperword, .delivery_details span.zone2, .outsource.modal .continuebtn, .outsource #changeTimezone,.outsource #changecurrency,.paymentinfo,.modal.outsource .contact_box, .modal.outsource .more, .needitfaster').addClass('hide');
    expandOutsourcePopup();
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
    expandOutsourcePopup();
}


function renderLocalizationInfos( price, delivery, revision_price, revision_delivery ) {
    // if the customer has a timezone in the cookie, then use it
    // otherwise attemp to guess it from his browser infos
    var timezoneToShow = readCookie( "matecat_timezone" );
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
    var currToShow = readCookie( "matecat_currency" );
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
    expandOutsourcePopup();
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
        $('.outsource.modal .minus').hide();
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

        setCookie( "matecat_currency", currencyTo );
    });
}


function fetchChangeRates( callback ) {
    if( UI.changeRates != "" ) {
        if( typeof callback == "function" ) callback();
        return;
    }

    var changeRates = readCookie( "matecat_changeRates" );
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
            setCookie( "matecat_changeRates", d.data, new Date( now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59 ) );
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

    setCookie( "matecat_timezone", timezoneTo );
}


function updateTimezonesDescriptions( selectedTimezone ) {
    $( "#changeTimezone" ).find( "option").each( function() {
        $( this ).text( $( this ).attr( "data-description-long" ) );
    });

    var selectedElement = $( "#changeTimezone" ).find( "option[value='" + selectedTimezone + "']");
    selectedElement.text( selectedElement.attr( "data-description-short" ) );
}


function readCookie( cookieName ) {
    cookieName += "=";
    var cookies = document.cookie.split(';');

    for ( var i = 0; i < cookies.length; i++ ) {
        var cookie = cookies[i].trim();

        if ( cookie.indexOf( cookieName ) == 0 )
            return cookie.substring( cookieName.length, cookie.length );
    }
    return "";
}


function setCookie( cookieName, cookieValue, expiration ) {
    if( typeof expiration == "undefined" ) {
        expiration = new Date();
        expiration.setYear(new Date().getFullYear() + 1);
    }
    document.cookie = cookieName + "=" + cookieValue + "; expires=" + expiration.toUTCString() + "; path=/";
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
    $('.delivery').appendTo(".delivery_container").attr("class","delivery");
    $('.outsource.modal .continuebtn').addClass('disabled');
    $('.tprice').removeClass('blink');
    $('.modal.outsource .continuebtn, .modal.outsource .contact_box,.paymentinfo,.outsource #changeTimezone,.outsource #changecurrency,.addrevision, .guaranteed_by .more, .delivery_details span.time, .delivery_label,.euro,.displayprice,.displaypriceperword, .delivery_details span.zone2, .needitfaster, .showpricesloading').removeClass('hide');
    $('.ErrorMsg,.modal.outsource .tooltip, .outsource_notify, .delivery_before_time, .checkstatus, #delivery_not_available, .trustbox2, .translator_info_box, .hide_translator.more, .showprices').addClass('hide');
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


