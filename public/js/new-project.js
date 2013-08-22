 
$(document).ready(function() {

    $('#create_private_tm_btn').click(function (){
        //prevent double click
        if($(this).hasClass('disabled')) return false;
        //show spinner
        $('#get-new-tm-spinner').show();
        //disable button
        $(this).addClass('disabled');
        $(this).attr('disabled','');
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
            //hide spinner
            $('#get-new-tm-spinner').hide();
            return false;	
        })
    })

    $(".more").click(function(e){
        e.preventDefault();
        $(".advanced-box").toggle('fast');
        $(".more").toggleClass('minus');
    });

    $("#source-lang").on('change', function(e){
		console.log('source language changed');
		var num = 0;
		$('.template-download .name').each(function(){
			if($(this).parent('tr').hasClass('failed')) return;
			if($(this).text().split('.')[$(this).text().split('.').length-1] != 'sdlxliff') num++;
		});
		if(!$('.template-download').length) return;
        if (num) {
	        var m = confirm('Source language changed. The files must be reimported.');
	        if(m) {
	            UI.restartConversions();
	        }            	
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
                tms_engine: $('#tms_engine').val(),
                mt_engine: $('#mt_engine').val(),
                private_tm_key: $('#private-tm-key').val(),
                private_tm_user: $('#private-tm-user').val(),
                private_tm_pass: $('#private-tm-pass').val()
            },
            context: ob,
            beforeSend: function (){
                $('.error-message').hide();
                $('.uploadbtn').attr('value','Analyzing...').attr('disabled','disabled').addClass('disabled');
            },
            success: function(d){
                if(d.error.length) {
                    $('.error-message').text('');
                    $.each(d.error, function() {
                        $('.error-message').append(this.message+'<br />').show();
                    });
                    $('body').removeClass('creating');
//                    var btnTxt = (config.analysisEnabled)? 'Analyze' : 'Translate';
//                    $('.uploadbtn').attr('value',btnTxt).removeClass('disabled').removeAttr('disabled');
                } else {
                    var btnTxt = (config.analysisEnabled)? 'Analyze' : 'Translate';
                    $('.uploadbtn').attr('value',btnTxt).removeClass('disabled').removeAttr('disabled');
                    //							$.cookie('upload_session', null);
                    if(config.analysisEnabled) {
                        location.href = '/analyze/' + d.project_name + '/' + d.id_project + '-' + d.ppassword;
                    } else {
                        location.href = '/translate/' + d.project_name + '/' + d.source_language.substring(0,2) + '-' + d.target_language.substring(0,2) + '/' + d.id_job + '-' + d.password;
                    //                    	$('.uploadbtn').attr('value','Analyze')
                    }
                //					location.href = '/translate/' + d.project_name + '/' + d.source_language.substring(0,2) + '-' + d.target_language.substring(0,2) + '/' + d.id_job + '-' + d.password;
                }

            }
        });
    });    		
  
    $("#multiple-link").click(function(e) {          
        e.preventDefault();
        $("div.grayed").fadeIn();
        $("div.popup-languages").fadeIn('fast');
        var tlAr = $('#target-lang').val().split(',');
        $.each(tlAr, function() {
	        var ll = $('.popup-languages .listlang li #'+this);
	        ll.parent().addClass('on');
	        ll.attr('checked','checked');
        });
        $('.popup-languages .header .number').text($(".popup-languages .listlang li.on").length);
    });
			
	$(".popup-languages .listlang li label").click(function(e) {          
        $(this).parent().toggleClass('on');
        var c = $(this).parent().find('input');
        if(c.attr('checked') == 'checked') {
        	c.removeAttr('checked');        	
        } else {
        	c.attr('checked','checked');
        }
        $('.popup-languages .header .number').text($(".popup-languages .listlang li.on").length);
    });
	$(".popup-languages .listlang li input").click(function(e) {          
        $(this).parent().toggleClass('on');
        $('.popup-languages .header .number').text($(".popup-languages .listlang li.on").length);
    });		
			
    $(".close").click(function(e) {          
        $("div.popup-languages").hide();
        $("div.grayed").hide();
    });

    $("#target-lang").change(function(e) {          
        $('.popup-languages li.on').each(function(){
			$(this).removeClass('on').find('input').removeAttr('checked');
		});
		$('.translate-box.target h2 .extra').remove();
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

