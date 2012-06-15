/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */







$(document).ready(function() {
	$(document).bind('keydown','Ctrl+return', function(e){
		$(document).find(".editor").find(" .Translated").click();
		//console.log(e)
	});
 $(document).ready(function() {
   $(".target-textarea").first().click()
 });





/*
$(".draggable").draggable();
    $(".target-textarea").droppable({
      drop: function() { alert('dropped'); }
    });
*/

					   
					   




$(".target-textarea").bind('keydown','Ctrl+return', function(e){
		e.preventDefault();
                $(document).find(".editor").find(" .Translated").click();
               // console.log(e)
        });

    	$('.sbm').tabify();
	jQuery('textarea').trigger('update');
	
	$('textarea').autogrow();
	
		
    $("div.notification-box").mouseup(function() {
        return false
    });
                
    $(document).mouseup(function(e) {
        if($(e.target).parent("a.m-notification").length==0) {
            $(".m-notification").removeClass("menu-open");
            $("fieldset#signin_menu").hide();
        }
    });	
                        
    $(".search-icon, .search-on").click(function(e) {          
        e.preventDefault();
        $("div#search").toggle();
    });
                
      
	  
	//overlay
	
	

	$(".more").click(function(){
$('.boxoverlay').fadeIn('fast');
$('#box').fadeIn('fast');
$('div.stats').hide('');

});
	
$("#close-overlay").click(function(){
$('.boxoverlay').fadeOut('fast');
$('#box').fadeOut('slow');

});


$(".preview-link").click(function(){
 $(".preview").addClass("heightprev");
 //console.log("pippo");
});


		   $(".x-stats").click(function(e) {          
      
   
  $(".stats").toggle();
    });


/*
    $(".corner").click(function(e) {          
				e.preventDefault();
				 $(this).parents(".ed").find(".h-notification").removeClass("c-close");
					 $(this).parents(".ed").find(".h-notification").show("slide", {
                direction: "left"
            }, 650);
				$(this).parents(".ed").find(".comment-area").addClass("openarea");
			 $(this).parents(".ed").find(".c-toggle").show("fold", {
                direction: "vertical"
            }, 650);
			$(".text-c").focus();
		 $(".main").animate({width: '71%'});	
				$(".main").addClass("maincomment");

});

  */
  
  
      $(".corner").click(function(e) {          
				e.preventDefault();
				
			 $(".comment-area").hide();	
		$(".h-notification").show();
			
			 $(".main").animate({width: '74%'});	
				$(".main").addClass("maincomment");	
				$(this).parents(".ed").find(".comment-area").addClass("openarea");
				
					
				
			 $(this).parents(".ed").find(".comment-area").show("slide", {
                direction: "left"
            }, 400);
			$(".text-c").focus();
		$(this).parents(".ed").find(".c-close").hide();
});
	  
	  
	  
  
  














            $(".x-com").click(function(e) {          
				e.preventDefault();

						$(this).parents(".ed").find(".comment-area").removeClass("openarea");
               $(this).parents(".ed").find(".comment-area").hide("slide", {
                direction: "left"
            }, 400);
			   
			   
			   			$(this).parents(".ed").find(".h-notification").show();
				 $(".main").removeClass("maincomment");
			    		$(".main").animate({width: '90.5%'});	
						$(".main").removeClass("maincomment");
						
           });

	  $(".x-addcom").click(function(e) {          
				e.preventDefault();

			 $(this).parents(".ed").find(".h-notification").show();
				 $(".main").removeClass("maincomment");
			    		$(".main").animate({width: '88.1%'});	
						$(".main").removeClass("maincomment");
						
           });
	
	    $(".addsuggestion").click(function(e) {          
				e.preventDefault();
						$(this).parents(".ed").find(".addline-suggestion").slideDown('fast', function() {
    // Animation complete.
  });
	  
                     });
		
		
		
    $(".status").click(function(e) {         
        e.preventDefault();
		 e.stopPropagation();
		 
		 var isVisible=$(this).parents(".ed").find(".menucolor").is(":visible");
		 
		  $(".menucolor:visible").hide();
		  if (isVisible){
			  return null;
		  }
		  
       $(this).parents(".ed").find(".menucolor").toggle();
    });



    $(".m-notification").click(function(e) {          
        e.preventDefault();
		 e.stopPropagation();
		 
		  var isVisible=$(".notification-box").is(":visible");
		
		  if (isVisible){  $(".notification-box").hide();
			  return null;
		  }
        $("div.notification-box").toggle();
        $(".m-notification").toggleClass("menu-open");
    });
	
	
	
	
	
	
	
	
	    $(".joblink").click(function(e) {          
        $(".joblist").toggle();
    });
		
		
		   $(".statslink").click(function(e) {          
      
   e.preventDefault();
		 e.stopPropagation();
  $(".stats").toggle();
    });
	
	
	
	
	
	


 $('html').click(function() {
 $(".menucolor").hide();
// 	 $(".notification-box").hide();
		 
		
 });



 
 
  
   $(".submenu a, .joblink").click(function(e) {          
	  return null;
    });
 

	
                
    $(".con-menubtn").click(function(e) {          
        e.preventDefault();
	   $("ul#col-menu").toggle();
    });
			
 
   $(".percentuage").click(function(e) {          
		 e.preventDefault();
		e.stopPropagation();
  
    });


$(".smart-suggestion-target").hide();
$(".smart-suggestion-source").each(function(e) { 
$(this).hover(function(e){
					   $(this).hide();
					   $(this).siblings(".smart-suggestion-target").show().attr("style","background-color:#3297fd;color:#fff");

   });

   });


$(".smart-suggestion-target").each(function(e) { 
$(this).mouseleave(function(e){
					   $(this).hide();
					   $(this).siblings(".smart-suggestion-source").show();
					   })

   });



    $(".target-textarea").click(function(e) {          
		 e.preventDefault();
		e.stopPropagation();
		   $(".menucolor:visible").hide();
		   
		   var anchor=($(this).parents(".ed")).prev();//.find(".number");
		   var anchor2=anchor.find(".number");
		   
		   //console.log(anchor2);
		   
		  if ( $(this).parents(".ed").find(".toggle").is(":visible")){return null}
			
			
			
			$(".editor:visible").find(".x").click();
   			$(".target-textarea").addClass("grayed-text");
          
            $("div.grayed").toggle();	
            /* console.log ($(this).parents(".ed"));*/
            $(this).parents(".ed").addClass("editor");
            $(this).focus();	
            $(this).parents(".ed").find(".toggle").show("blind", {
                direction: "vertical"
            }, 250);
        
        //  $(this).removeClass("editor-click");
    })
	

	
	


	
		 $(".search-icon").click(function(){
      $(".main").addClass("main-searched");
    })
	 $(".search-on").click(function(){
      $(".main").removeClass("main-searched");
    })
	
    
    $(".draft, .Translated, .approved").click(function(){
        console.log ($(this).parents(".ed"));
		 $(".target-textarea").addClass("grayed-text");
        $(this).parents(".ed").find(".x").click();
		  $(this).parents(".ed").find(".status").removeClass("col-approved");
		  $(this).parents(".ed").find(".status").removeClass("col-notapproved");
    $(this).parents(".ed").find(".status").removeClass("col-done");
    $(this).parents(".ed").find(".status").removeClass("col-draft");
	
    })
    
    $(".Translated").click(function(){
      $(this).parents(".ed").find(".status").addClass("col-translated");
    })
	
	   $(".draft").click(function(){
      $(this).parents(".ed").find(".status").addClass("col-draft");
    })
	   
	      $(".approved").click(function(){
      $(this).parents(".ed").find(".status").addClass("col-approved");
    })
	   
	
	$(".d, .a, .r, .f").click(function(){
		  $(this).parents(".ed").find(".status").removeClass("col-approved");
		  $(this).parents(".ed").find(".status").removeClass("col-notapproved");
    $(this).parents(".ed").find(".status").removeClass("col-done");
    $(this).parents(".ed").find(".status").removeClass("col-draft");
    })
	
	
	 $(".d").click(function(){
      $(this).parents(".ed").find(".status").addClass("col-translated");
    })
	 
	  $(".a").click(function(){
      $(this).parents(".ed").find(".status").addClass("col-approved");
    })
	  
	    $(".r").click(function(){
      $(this).parents(".ed").find(".status").addClass("col-notapproved");
    })
		
		   $(".f").click(function(){
      $(this).parents(".ed").find(".status").addClass("col-draft");
    })
		
		
	
	
    $(".copysource").click(function(){
        //console.log ("cp");
        var source_val=$.trim($(this).parents(".ed").find("li.source").html());
        //console.log(source_val)
        $(this).parents(".ed").find("li.target>textarea").val(source_val).keyup().focus();
     $(this).parents(".ed").find("li.target>textarea").effect("highlight", {}, 1000);
    })
        

	
			
    $(".x").click(function(e) {          
        //console.log("fff");
        e.preventDefault();
        $(this).parents(".ed").find(".toggle").hide("blind", {
            direction: "vertical"
        },250);
        $("div.grayed").toggle();
            $(".target-textarea").removeClass("grayed-text");
        //$(this).parents(".ed").removeClass("editor").find(".editable_textarea").find("button").promise(function(){
        $(this).parents(".ed").find(".toggle").promise().done(function(){
            $(this).parents(".ed").removeClass("editor").find(".editable_textarea").find("button").click();
            /* console.log($(this)) */
                 
        })
				
    });
                   
});
	
	
	
  
