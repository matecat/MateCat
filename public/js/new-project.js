 
        $(document).ready(function() {

$(".more").click(function(e){
	    	e.preventDefault();
  			$(".advanced-box").toggle('fast');
			$(".more").toggleClass('minus');
		});
         
			$("input.uploadbtn").click(function(e) {
				$('body').addClass('creating');
				var files = '';
				$('.upload-table td.name').each(function () {
        			files += ',' + $(this).text();
		        });

		        $.ajax({
		            url: window.location.href,
		            data: {
		                action: 'createProject',
		                file_name: files.substr(1),
		                project_name: $('#project-name').val(),
		                source_language: $('#source-lang').val(),
                target_language: $('#target-lang').val(),
                tms_engine: $('#tms_engine').val(),
                mt_engine: $('#mt_engine').val()
		            },
		            type: 'POST',            
		            dataType: 'json',
//		            context: $('#'+id),
		            beforeSend: function (){
			            $('.error-message').hide();
		            	$('.uploadbtn').attr('value','Analizing...').attr('disabled','disabled').addClass('disabled');
		            },
		            complete: function (){
		            },
		            success: function(d){
//		            	console.log(d.password + ' - ' + d.job_id);
						if(d.errors.length) {
							$('.error-message').text('');
			                $.each(d.errors, function() {
			                	$('.error-message').append(this.message+'<br />').show();
			                });
			                $('body').removeClass('creating');
		            		$('.uploadbtn').attr('value','Start Translating').removeAttr('disabled');
						} else {
//							$.cookie('upload_session', null);
							location.href = '/translate/' + d.project_name + '/' + d.source_language.substring(0,2) + '-' + d.target_language.substring(0,2) + '/' + d.id_job + '-' + d.password;
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
		        if($('.upload-table tr').length) $('.uploadbtn').removeAttr('disabled').removeClass('disabled');
    		});
			$("input").keyup(function(e) {          
				$('.error-message').hide();
		        if($('.upload-table tr').length) $('.uploadbtn').removeAttr('disabled').removeClass('disabled');
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
