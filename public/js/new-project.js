$(document).ready(function() {

	$('#create_private_tm_btn').click(function() {

		//prevent double click
		if ( $( this ).hasClass( 'disabled' ) ) return false;

		//show spinner
		//$('#get-new-tm-spinner').show();
		//disable button
		$(this).addClass('disabled');
		$(this).attr('disabled','');
		if(typeof $(this).attr('data-key') == 'undefined') {

            //call API
            APP.doRequest( {
                data: {
                    action: 'createRandUser'
                },
                success: function ( d ) {
                    $( '#private-tm-key' ).val( d.data.key );
                    $( '#private-tm-user' ).val( d.data.id );
                    $( '#private-tm-pass' ).val( d.data.pass );
                    $( '#create_private_tm_btn' ).attr( 'data-key', d.data.key );

					$( 'tr.template-download.fade.ready ').each( function( key, fileUploadedRow ){

						var _fileName = $( fileUploadedRow ).find( '.name' ).text();
						if ( _fileName.split('.').pop().toLowerCase() == 'tmx' ) {

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

		} else {
			$('#private-tm-key').val($(this).attr('data-key'));
		}

	});

	$(".more").click(function(e){
		e.preventDefault();
		$(".advanced-box").toggle('fast');
		$(".more").toggleClass('minus');
	});

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
				action				: "createProject",
				file_name			: filename,
				project_name		: $('#project-name').val(),
				source_language		: $('#source-lang').val(),
				target_language		: $('#target-lang').val(),
                job_subject         : $('#subject').val(),
                disable_tms_engine	: ( $('#disable_tms_engine').prop('checked') ) ? $('#disable_tms_engine').val() : false,
				mt_engine			: $('#mt_engine').val(),
                private_tm_key		: $('#private-tm-key').val(),
                private_keys_list	: tm_data,
				private_tm_user		: ( !$('#private-tm-user').prop('disabled') ? $('#private-tm-user').val() : "" ),
				private_tm_pass		: ( !$('#private-tm-pass').prop('disabled') ? $('#private-tm-pass').val() : "" ),
				lang_detect_files  	: UI.skipLangDetectArr,
                pretranslate_100    : ($("#pretranslate100" ).is(':checked')) ? 1 : 0,
                dqf_key             : ($('#dqf_key' ).length == 1) ? $('#dqf_key' ).val() : null,
				lexiqa				: ( $("#lexi_qa").prop("checked") && !$("#lexi_qa").prop("disabled") )
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
								$(".more, #multiple-link").unbind('click').on('click',function(e){
									e.preventDefault();
								}).addClass('disabledLink');
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
    $("#source-lang").on('change', function(){
		APP.checkForLexiQALangs();
		APP.checkForTagProjectionLangs();
	});
    $("#target-lang").on('change', function(){
		APP.checkForLexiQALangs();
		APP.checkForTagProjectionLangs();
	});

    function closeMLPanel() {
        $( ".popup-languages.slide").removeClass('open').hide("slide", { direction: "right" }, 400);
        $("#SnapABug_Button").show();
        $(".popup-outer.lang-slide").hide();
        $('body').removeClass('side-popup');

        APP.checkForLexiQALangs();
        APP.checkForTagProjectionLangs();
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
			$("#create_private_tm_btn").addClass("disabled", true);
		} else {
			if(!$('#create_private_tm_btn[data-key]').length) {
				$("input[id^='private-tm-']").prop("disabled", false);
				$("#create_private_tm_btn").removeClass("disabled");
			}
		}
	});

	$("#private-tm-key").on('keyup', function(e) {
		if($(this).val() == '') {
			$('#create_private_tm_btn').removeClass('disabled');
			$('#create_private_tm_btn').removeAttr('disabled');
		} else {
			$('#create_private_tm_btn').addClass('disabled');
			$('#create_private_tm_btn').attr('disabled','disabled');
		};
	});

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


/**
 * Disable/Enable languages for LexiQA
 *
 */
APP.checkForLexiQALangs = function(){

	var acceptedLanguages = [
		'en-US',
		'en-GB',
		'fr-FR',
		'de-DE',
		'it-IT'
	];

    var disableLexiQA = acceptedLanguages.concat(
            [ $( '#source-lang' ).val() ]
        ).concat(
            $( '#target-lang' ).val().split(',')
        ).filter(
            function ( value, index, self ) {
                return self.indexOf( value ) === index;
            }
        ).length !== acceptedLanguages.length;

    //disable LexiQA
    $('.options-box #lexi_qa').prop( "disabled", disableLexiQA );
    $('.options-box.qa-box').css({opacity: ( disableLexiQA ? 0.6 : 1 )  });

};

/**
 * Disable/Enable languages for LexiQA
 *
 */
APP.checkForTagProjectionLangs = function(){

	var acceptedLanguages = [
		'en-US',
		'en-GB',
		'it-IT'
	];

	var disableTP = acceptedLanguages.concat(
			[ $( '#source-lang' ).val() ]
		).concat(
			$( '#target-lang' ).val().split(',')
		).filter(
			function ( value, index, self ) {
				return self.indexOf( value ) === index;
			}
		).length !== acceptedLanguages.length;

	//disable Tag Projection
	$('.options-box #tagp_check').prop( "disabled", disableTP );
	$('.options-box.tagp').css({opacity: ( disableTP ? 0.6 : 1 )  });

};
