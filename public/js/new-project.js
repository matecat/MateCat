$(document).ready(function() {

	$( "a.more-options" ).on("click", function ( e ) {
		e.preventDefault();
		APP.openOptionsPanel("opt")
	} );

	$("#source-lang").on('change', function(e){
            console.log('source language changed');
            UI.checkRTL();
            if($('.template-download').length) { //.template-download is present when jquery file upload is used and a file is found
                if (UI.conversionsAreToRestart()) {
                    APP.confirm({msg: 'Source language has been changed.<br/>The files will be reimported.', callback: 'confirmRestartConversions'});
                }
                if( UI.checkTMXLangFailure() ){
                    UI.delTMXLangFailure();
                }
            }
            else if ($('.template-gdrive').length) {
                APP.confirm({
                    msg: 'Source language has been changed.<br/>The files will be reimported.',
                    callback: 'confirmGDriveRestartConversions'
                });
            } else {
                return;
            }
	});

        APP.tryListGDriveFiles();

	$("#target-lang").change(function(e) {

        UI.checkRTL();
		$('.popup-languages li.on').each(function(){
			$(this).removeClass('on').find('input').removeAttr('checked');
		});
		$('.translate-box.target h2 .extra').remove();
		if( UI.checkTMXLangFailure() ){
			UI.delTMXLangFailure();
		}
		APP.changeTargetLang( $(this).val() );
		
    });

	$("input.uploadbtn").click(function(e) {
        
        if(!UI.allTMUploadsCompleted()) {
            return false;
        }
		$('body').addClass('creating');
		var files = '';
		$('.upload-table tr:not(.failed) td.name, .gdrive-upload-table tr:not(.failed) td.name').each(function () {
			files += '@@SEP@@' + $(this).text();
		});

        tm_data = UI.extractTMdataFromTable();

        var filename = files.substr(7) ;

		APP.doRequest({
			data: {
				action						: "createProject",
				file_name					: filename,
				project_name				: $('#project-name').val(),
				source_language				: $('#source-lang').val(),
				target_language				: $('#target-lang').val(),
                job_subject         		: $('#subject').val(),
                disable_tms_engine			: ( $('#disable_tms_engine').prop('checked') ) ? $('#disable_tms_engine').val() : false,
				mt_engine					: ($('.enable-mt input').prop("checked")) ? 1 : 0,
                private_tm_key				: $('#private-tm-key').val(),
                private_keys_list			: tm_data,
				private_tm_user				: ( !$('#private-tm-user').prop('disabled') ? $('#private-tm-user').val() : "" ),
				private_tm_pass				: ( !$('#private-tm-pass').prop('disabled') ? $('#private-tm-pass').val() : "" ),
				lang_detect_files  			: UI.skipLangDetectArr,
                pretranslate_100    		: ($("#pretranslate100" ).is(':checked')) ? 1 : 0,
                dqf_key             		: ($('#dqf_key' ).length == 1) ? $('#dqf_key' ).val() : null,
				lexiqa				        : ( $("#lexi_qa").prop("checked") && !$("#lexi_qa").prop("disabled") ),
				speech2text         		: ( $("#s2t_check").prop("checked") && !$("#s2t_check").prop("disabled") ),
				tag_projection			    : ( $("#tagp_check").prop("checked") && !$("#tagp_check").prop("disabled") )
			},
			beforeSend: function (){
				$('.error-message').hide();
				$('.uploadbtn').attr('value','Analyzing...').attr('disabled','disabled').addClass('disabled');
			},
			success: function(d){
				console.log('d: ', d);

				if(typeof(d.lang_detect) !== 'undefined'){
					UI.skipLangDetectArr = d.lang_detect;
				}

                if ( UI.skipLangDetectArr != null ) {
                    $.each(UI.skipLangDetectArr, function(file, status){
                        if(status == 'ok') 	UI.skipLangDetectArr[file] = 'skip';
                        else UI.skipLangDetectArr[file] = 'detect';

                    });
                }


				if( typeof d.errors != 'undefined' && d.errors.length ) {

					var alertComposedMessage = [];
					$('.error-message').text('');

					$.each(d.errors, function() {

						switch(this.code) {
							//no useful memories found in TMX
							case -16 :
                                UI.addTMXLangFailure();
								break;
                            case -14 :
                                UI.addInlineMessage(
                                    ".tmx",
                                    this.message
                                );
                                break;
							//no text to translate found.
							case -1  : 	var fileName = this.message.replace("No text to translate in the file ", "")
								.replace(/.$/g,"");

								console.log(fileName);
								UI.addInlineMessage(
									fileName,
									'Is this a scanned file or image?<br/>Try converting to DOCX using an OCR software '+
										'(ABBYY FineReader or Nuance PDF Converter)'
								);
								break;
							case -17  :
								$.each(d.lang_detect, function (fileName, status){
									if(status == 'detect'){
										UI.addInlineMessage(
											fileName,
											'Different source language. <a class="skip_link" id="skip_'+fileName+'">Ignore</a>'
										);
									}
								});
								break;

							default:
						}

						//normal error management
						$('.error-message').append( '<div class="error-content">' + this.message + '<br /></div>' ).show();

					});

					$('.uploadbtn').attr('value', 'Analyze');
					$('body').removeClass('creating');

				} else {

                    //reset the clearNotCompletedUploads event that should be called in main.js onbeforeunload
                    //--> we don't want to delete the files on the upload directory
                    clearNotCompletedUploads = function(){};

					if( config.analysisEnabled ) {

						//this should not be.
						//A project now are never EMPTY, it is not created anymore
						if( d.status == 'EMPTY' ){

							console.log('EMPTY');
							$('body').removeClass('creating');
							APP.alert({msg: 'No text to translate in the file(s).<br />Perhaps it is a scanned file or an image?'});
							$('.uploadbtn').attr('value','Analyze').removeAttr('disabled').removeClass('disabled');

						} else {
                            location.href = d.analyze_url ;
						}

					} else {

						if( Object.keys( d.target_language ).length > 1 ){ //if multiple language selected show a job list
							d.files = [];
							d.trgLangHumanReadable = $('#target-lang option:selected').text().split(',');
							d.srcLangHumanReadable = $('#source-lang option:selected').text();

							$.each( d.target_language, function( idx, val ){
								d.files.push({ href: config.hostpath + config.basepath + 'translate/' + d.project_name + '/' + d.source_language.substring(0,2) + '-' + val.substring(0,2) + '/' + d.id_job[idx] + '-' + d.password[idx] });
							} );

							$('.uploadbtn-box').fadeOut('slow', function(){
								$('.uploadbtn-box').replaceWith( tmpl("job-links-list", d));

								var btnContainer = $('.btncontinue');
								var btnNew = $('#add-files').clone();
								btnContainer.fadeOut('slow',function () {
									btnContainer.html('').addClass('newProject');
									btnNew.children('span').text('New Project');
									btnNew.children('i').remove();
									btnNew.children('input').remove();
									btnNew.attr({id: 'new-project'}).on('click',function () {
										location.href = config.hostpath + config.basepath;
									}).css({margin: 'auto 0'});
									btnNew.appendTo(btnContainer);
								}).css({height: '50px'}).fadeIn(1000);

								$('.translate-box input, .translate-box select').attr({disabled:'disabled'});
								$('td.delete').empty();
								$('#info-login').fadeIn(1000);
								$('#project-' + d.id_project).fadeIn(1000);

							});

						} else {
							location.href = config.hostpath + config.basepath + 'translate/' + d.project_name + '/' + d.source_language.substring(0,2) + '-' + d.target_language[0].substring(0,2) + '/' + d.id_job[0] + '-' + d.password[0];
						}

					}
				}

			}
		});
	});

	$('.upload-table').on('click', 'a.skip_link', function(){
		var fname = decodeURIComponent($(this).attr("id").replace("skip_",""));

		UI.skipLangDetectArr[fname] = 'skip';

		var parentTd_label = $(this).parent(".label");

		$(parentTd_label)
			.fadeOut(200, function(){
				$(this).remove()
			});
		$(parentTd_label).parent().removeClass("error");

		//analyze button should be reactivated?
		if($('.upload-table td.error').length == 0){
			$('.uploadbtn').removeAttr("disabled").removeClass("disabled").focus();
		}
	});

    /**
     * LexiQA language Enable/Disable
     */
    APP.checkForLexiQALangs();
    APP.checkForTagProjectionLangs();
	APP.checkForSpeechToText();
    $("#source-lang").on('change', function(){
		APP.checkForLexiQALangs();
		APP.checkForTagProjectionLangs();
	});
    $("#target-lang").on('change', function(){
		APP.checkForLexiQALangs();
		APP.checkForTagProjectionLangs();
	});

	APP.openOptionsPanel = function (tab, elem) {
		elToClick = $(elem).attr('data-el-to-click') || null;
		UI.openLanguageResourcesPanel(tab, elToClick);
	};

	APP.createTMKey = function () {

		if($(".mgmt-tm .new .privatekey .btn-ok").hasClass('disabled')) {
			return false;
		}


		//call API
		APP.doRequest( {
			data: {
				action: 'createRandUser'
			},
			success: function ( d ) {
				/*$( '#private-tm-key' ).val( d.data.key );
				$( '#private-tm-user' ).val( d.data.id );
				$( '#private-tm-pass' ).val( d.data.pass );
				$( '#create_private_tm_btn' ).attr( 'data-key', d.data.key );*/

				$( 'tr.template-download.fade.ready ').each( function( key, fileUploadedRow ){

					var _fileName = $( fileUploadedRow ).find( '.name' ).text();
					if ( _fileName.split('.').pop().toLowerCase() == 'tmx' || _fileName.split('.').pop().toLowerCase() == 'g' ) {

						UI.appendNewTmKeyToPanel( {
							r: 1,
							w: 1,
							desc: _fileName,
							TMKey: d.data.key
						} );

						return true;
					}

				});

				return false;
			}
		} );


	};

    function closeMLPanel() {
        $( ".popup-languages.slide").removeClass('open').hide("slide", { direction: "right" }, 400);
        $("#SnapABug_Button").show();
        $(".popup-outer.lang-slide").hide();
        $('body').removeClass('side-popup');

        APP.checkForLexiQALangs();
        APP.checkForTagProjectionLangs();
		APP.checkForSpeechToText();
    };

	$("#multiple-link").click(function(e) {
        e.preventDefault();
        $(".popup-languages.slide").addClass('open').show("slide", { direction: "right" }, 400);
        var tlAr = $('#target-lang').val().split(',');
        $.each(tlAr, function() {
            var ll = $('.popup-languages.slide .listlang li #' + this);
            ll.parent().addClass('on');
            ll.attr('checked','checked');
        });
        $("#SnapABug_Button").hide();
        $(".popup-outer.lang-slide").show();
        $('body').addClass('side-popup');
	});
	
	$(".popup-outer.lang-slide, #cancelMultilang, #chooseMultilang").click(function(e) {
		closeMLPanel();
	});

	$(".popup-languages .listlang li label").click(function(e) {
		$(this).parent().toggleClass('on');
		var c = $(this).parent().find('input');
		if(c.attr('checked') == 'checked') {
			c.removeAttr('checked');
		} else {
			c.attr('checked','checked');
		}
		$('.popup-languages h1 .number').text($(".popup-languages .listlang li.on").length);
	});
	$(".popup-languages .listlang li input").click(function(e) {
		$(this).parent().toggleClass('on');
		$('.popup-languages h1 .number').text($(".popup-languages .listlang li.on").length);
	});

	$(".close").click(function(e) {
		$("div.popup-languages").hide();
		$("div.grayed").hide();
	});

	$("#disable_tms_engine").change(function(e){
		if(this.checked){
			$("input[id^='private-tm-']").prop("disabled", true);
			// $("#create_private_tm_btn").addClass("disabled", true);
		} else {
			if(!$('#create_private_tm_btn[data-key]').length) {
				$("input[id^='private-tm-']").prop("disabled", false);
				$("#create_private_tm_btn").removeClass("disabled");
			}
		}
	});

	/*$("#private-tm-key").on('keyup', function(e) {
		if($(this).val() == '') {
			$('#create_private_tm_btn').removeClass('disabled');
			$('#create_private_tm_btn').removeAttr('disabled');
		} else {
			$('#create_private_tm_btn').addClass('disabled');
			$('#create_private_tm_btn').attr('disabled','disabled');
		};
	});*/

	$("input, select").change(function(e) {
		$('.error-message').hide();
	});
	$("input").keyup(function(e) {
		$('.error-message').hide();
	});

});

/**
 * ajax call to clear the uploaded files when an user refresh the home page
 * called in main.js
 */
clearNotCompletedUploads = function() {
    $.ajax({
        async: false,
        url: config.basepath + '?action=ajaxUtils&' + ( new Date().getTime() ),
        data: {exec:'clearNotCompletedUploads'},
        type: 'POST',
        dataType: 'json'
    });
};

APP.changeTargetLang = function( lang ) {
    if( localStorage.getItem( 'currentTargetLang' ) != lang ) {
        localStorage.setItem( 'currentTargetLang', lang );
    }
};

APP.displayCurrentTargetLang = function() {
    $( '#target-lang' ).val( localStorage.getItem( 'currentTargetLang' ) );
};


function showModalNotSupportedLanguages(notAcceptedLanguages, acceptedLanguages) {
	APP.alert({
		title: 'Option not available',
		okTxt: 'Continue',
		msg: "Not available for " + notAcceptedLanguages.join(", ") +
		".</br> Only available for " + acceptedLanguages.join(", ") +"."
	});
}

function createSupportedLanguagesArrays(acceptedLanguages, targetLanguages, sourceAccepted) {
	var notAcceptedLanguagesNames = [], acceptedLanguagesNames = [];
	var notAcceptedLanguagesCodes = [], acceptedLanguagesCodes = [];
	var notAcceptedLanguages = targetLanguages.filter(function(n) {
		return acceptedLanguages.indexOf(n) === -1;
	});
	if (!sourceAccepted) {
		notAcceptedLanguages.push($( '#source-lang' ).val());
	}

	notAcceptedLanguages.forEach(function (value, index, array) {
		notAcceptedLanguagesNames.push($( '#target-lang option[value='+value+']' ).first().text());
		notAcceptedLanguagesCodes.push(value.split("-")[1]);
	});
	acceptedLanguages.forEach(function (value, index, array) {
		acceptedLanguagesNames.push($( '#target-lang option[value='+value+']' ).first().text());
		acceptedLanguagesCodes.push(value.split("-")[1]);
	});
	return {
		accepted: acceptedLanguagesNames,
		acceptedCodes: acceptedLanguagesCodes,
		notAccepted: notAcceptedLanguagesNames,
		notAcceptedCodes: notAcceptedLanguagesCodes
	};
}
/**
 * Disable/Enable languages for LexiQA
 *
 */
APP.checkForLexiQALangs = function(){

	var acceptedLanguages = config.lexiqa_languages.slice();
	var LXQCheck = $('.options-box.qa-box');

	var targetLanguages = $( '#target-lang' ).val().split(',');
	var sourceAccepted = (acceptedLanguages.indexOf($( '#source-lang' ).val() ) > -1);
	var targetAccepted = targetLanguages.filter(function(n) {
							return acceptedLanguages.indexOf(n) != -1;
						}).length > 0;
	LXQCheck.removeClass('option-unavailable');
    //disable LexiQA
	var disableLexiQA = !(sourceAccepted && targetAccepted && config.defaults.lexiqa);
	if (!(sourceAccepted && targetAccepted)) {
		var arrays = createSupportedLanguagesArrays(acceptedLanguages, targetLanguages, sourceAccepted);
		LXQCheck.find('.option-supported-languages').html(arrays.acceptedCodes.join(', '));
		LXQCheck.find('.option-notsupported-languages').html(arrays.notAcceptedCodes.join(', '));
		LXQCheck.find('.onoffswitch').off("click").on('click', function () {
			showModalNotSupportedLanguages(arrays.notAccepted, arrays.accepted);
		});
		LXQCheck.addClass('option-unavailable');
		$('.options-box #lexi_qa').prop( "disabled", disableLexiQA );
	}
    $('.options-box #lexi_qa').attr('checked', !disableLexiQA);
};

/**
 * Disable/Enable languages for LexiQA
 *
 */
APP.checkForTagProjectionLangs = function(){

	var acceptedLanguages = config.tag_projection_languages.slice();
	var tpCheck = $('.options-box.tagp');
	var targetLanguages = $( '#target-lang' ).val().split(',');
	var sourceAccepted = (acceptedLanguages.indexOf($( '#source-lang' ).val() ) > -1);
	var targetAccepted = targetLanguages.filter(function(n) {
							return acceptedLanguages.indexOf(n) != -1;
						}).length > 0;
	tpCheck.removeClass('option-unavailable');

	//disable Tag Projection
	var disableTP = !(sourceAccepted && targetAccepted && config.defaults.tag_projection);
	if (!(sourceAccepted && targetAccepted)) {
		var arrays = createSupportedLanguagesArrays(acceptedLanguages, targetLanguages, sourceAccepted);
		tpCheck.find('.option-supported-languages').html(arrays.acceptedCodes.join(', '));
		tpCheck.find('.option-notsupported-languages').html(arrays.notAcceptedCodes.join(', '));
		tpCheck.find('.onoffswitch').off('click').on('click', function () {
			showModalNotSupportedLanguages(arrays.notAccepted, arrays.accepted);
		});
		tpCheck.addClass('option-unavailable');
		$('.options-box #tagp_check').prop( "disabled", disableTP );
	}
	$('.options-box #tagp_check').attr('checked', !disableTP);
};

/**
 * Disable/Enable SpeechToText
 *
 */
APP.checkForSpeechToText = function(){

	//disable Tag Projection
	var disableS2T = !config.defaults.speech2text;
	var speech2textCheck = $('.s2t-box');
	speech2textCheck.removeClass('option-unavailable');
	if (!('webkitSpeechRecognition' in window)) {
		disableS2T = true;
		$('.options-box #s2t_check').prop( "disabled", disableS2T );
		speech2textCheck.find('.option-s2t-box-chrome-label').css('display', 'inline');
		speech2textCheck.find('.onoffswitch').on('click', function () {
			APP.alert({
				title: 'Option not available',
				okTxt: 'Continue',
				msg: "This options is only available on Chrome browser."
			});
		});
		speech2textCheck.addClass('option-unavailable');
	}
	$('.options-box #s2t_check').attr('checked', !disableS2T);
};
