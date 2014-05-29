 
$(document).ready(function() {

    $('#create_private_tm_btn').click(function() {
        //prevent double click
        if($(this).hasClass('disabled')) return false;
        //show spinner
        $('#get-new-tm-spinner').show();
        //disable button
        $(this).addClass('disabled');
        $(this).attr('disabled','');
		if(typeof $(this).attr('data-key') == 'undefined') {
			//call API
			$.get("http://mymemory.translated.net/api/createranduser",function(data){
				//parse to appropriate type
				//this is to avoid a curious bug in Chrome, that causes 'data' to be already an Object and not a json string
				if(typeof data == 'string'){
					data=jQuery.parseJSON(data);
				}
				//put value into input field
				$('#private-tm-key').val(data.key);
				$('#private-tm-user').val(data.id);
				$('#private-tm-pass').val(data.pass);
				$('#create_private_tm_btn').attr('data-key', data.key);
				//hide spinner
				$('#get-new-tm-spinner').hide();
				return false;	
			})			
		} else {
			$('#private-tm-key').val($(this).attr('data-key'));
		}
    })

    $(".more").click(function(e){
        e.preventDefault();
        $(".advanced-box").toggle('fast');
        $(".more").toggleClass('minus');
    });

    $("#source-lang").on('change', function(e){
        console.log('source language changed');
        if(!$('.template-download').length) return;
        if (UI.conversionsAreToRestart()) {
            APP.confirm({msg: 'Source language changed. The files must be reimported.', callback: 'confirmRestartConversions'});
        }
        if( UI.checkTMXLangFailure() ){
            UI.delTMXLangFailure();
        }
    });

    $("#target-lang").change(function(e) {
        $('.popup-languages li.on').each(function(){
            $(this).removeClass('on').find('input').removeAttr('checked');
        });
        $('.translate-box.target h2 .extra').remove();
        if( UI.checkTMXLangFailure() ){
            UI.delTMXLangFailure();
        }
    });

    $("input.uploadbtn").click(function(e) {
        $('body').addClass('creating');
        var files = '';
        $('.upload-table tr:not(.failed) td.name').each(function () {
            files += '@@SEP@@' + $(this).text();
        });

        APP.doRequest({
            data: {
                action:	 "createProject",
                file_name: files.substr(7),
                project_name: $('#project-name').val(),
                source_language: $('#source-lang').val(),
                target_language: $('#target-lang').val(),
                disable_tms_engine: ( $('#disable_tms_engine').prop('checked') ) ? $('#disable_tms_engine').val() : false,
                mt_engine: $('#mt_engine').val(),
                private_tm_key: ( !$('#private-tm-key').prop('disabled') ? $('#private-tm-key').val() : "" ),
                private_tm_user: ( !$('#private-tm-user').prop('disabled') ? $('#private-tm-user').val() : "" ),
                private_tm_pass: ( !$('#private-tm-pass').prop('disabled') ? $('#private-tm-pass').val() : "" )
            },
            beforeSend: function (){
                $('.error-message').hide();
                $('.uploadbtn').attr('value','Analyzing...').attr('disabled','disabled').addClass('disabled');
            },
            success: function(d){
				console.log('d: ', d);

                if( typeof d.errors != 'undefined' ) {

                    $('.error-message').text('');

                    $.each(d.errors, function() {

                        if( this.code == -16 ){
                            UI.addTMXLangFailure();
                        }

                        $('.error-message').append( '<div>' + this.message + '<br /></div>' ).show();
                    });

					$('.uploadbtn').attr('value', 'Analyze');
                    $('body').removeClass('creating');

//                    var btnTxt = (config.analysisEnabled)? 'Analyze' : 'Translate';
//                    $('.uploadbtn').attr('value',btnTxt).removeClass('disabled').removeAttr('disabled');

                } else {
                    //							$.cookie('upload_session', null);
                    if(config.analysisEnabled) {

                        if( d.status == 'EMPTY' ){
							console.log('EMPTY');
                            $('body').removeClass('creating');
                            APP.alert({msg: 'No text to translate in the file(s).<br />Perhaps it is a scanned file or an image?'});
                            $('.uploadbtn').attr('value','Analyze').removeAttr('disabled').removeClass('disabled');
                        } else {
                            location.href = config.hostpath + config.basepath + 'analyze/' + d.project_name + '/' + d.id_project + '-' + d.ppassword;
                        }

                    } else {

                        if( Object.keys( d.target_language ).length > 1 ){ //if multiple language selected show a job list
                            d.files = [];
                            d.trgLangHumanReadable = $('#target-lang option:selected').text().split(',');
                            d.srcLangHumanReadable = $('#source-lang option:selected').text();
                            //console.log(d);
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
  
    $("#multiple-link").click(function(e) {          
        e.preventDefault();
//        $("div.grayed").fadeIn();
//        $("div.popup-languages").fadeIn('fast');
        $(".popup-languages").show();

        var tlAr = $('#target-lang').val().split(',');
        $.each(tlAr, function() {
	        var ll = $('.popup-languages .listlang li #'+this);
	        ll.parent().addClass('on');
	        ll.attr('checked','checked');
        });
        $('.popup-languages h1 .number').text($(".popup-languages .listlang li.on").length);
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
    //		        if($('.upload-table tr').length) $('.uploadbtn').removeAttr('disabled').removeClass('disabled');
    });
    $("input").keyup(function(e) {          
        $('.error-message').hide();
    //		        if($('.upload-table tr').length) $('.uploadbtn').removeAttr('disabled').removeClass('disabled');
    });
//    		uploadSessionId = $.cookie("upload_session");

});

