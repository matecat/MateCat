/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

      
        $(document).ready(function() {
         
			$("input.uploadbtn").click(function(e) {

//		        console.log();
		        $.ajax({
		            url: window.location.href,
		            data: {
		                action: 'createProject',
		                file_name: $('.upload-table td.name').text(),
		                project_name: $('#project-name').val(),
		                source_language: $('#source-lang').val(),
		                target_language: $('#target-lang').val()
		            },
		            type: 'POST',            
		            dataType: 'json',
//		            context: $('#'+id),
		            beforeSend: function (){
		            	$('.uploadbtn').attr('value','Analizing...').attr('disabled','disabled');
		            },
		            complete: function (){
		            },
		            success: function(d){
//		            	console.log(d.password + ' - ' + d.job_id);
						$.cookie('upload_session', null);
						location.href='/translate/'+d.project_name+'/'+d.source_language.substring(0,2)+'-'+d.target_language.substring(0,2)+'/'+d.id_job+'-'+d.password;
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
    		
    		uploadSessionId = $.cookie("upload_session");



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
