 
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
        $.get("http://mymemory.translated.home/api/createranduser",function(data){
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
         
    $("input.uploadbtn").click(function(e) {
        $('body').addClass('creating');
        var files = '';
        $('.upload-table tr:not(.failed) td.name').each(function () {
            files += '@@SEP@@' + $(this).text();
        });

        $.ajax({
            url: window.location.href,
            data: {
                action: 'createProject',
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
            type: 'POST',            
            dataType: 'json',
            //		            context: $('#'+id),
            beforeSend: function (){
                $('.error-message').hide();
                $('.uploadbtn').attr('value','Analyzing...').attr('disabled','disabled').addClass('disabled');
            },
            complete: function (){
            },
            success: function(d){
                //		            	console.log(d.password + ' - ' + d.job_id);
                if(d.errors.length) {
                    console.log('errore');
                    $('.error-message').text('');
                    $.each(d.errors, function() {
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
        $("div.popup-languages").show();
        $("div.grayed").show();
    });
			
			
			
			
    $(".close").click(function(e) {          
        $("div.popup-languages").hide();
        $("div.grayed").hide();
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



/*
    		var uploadSession = $.cookie("upload_session");
//    		console.log(window.location);

		    $('#fileupload').fileupload({
		        uploadDir: window.location.href+'/storage/upload/'+uploadSession+'/'
		    });				
*/
});



 


/*  
*/
