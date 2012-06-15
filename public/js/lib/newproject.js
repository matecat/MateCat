/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */


$(document).ready(function() {
	$(document).bind('keydown','Ctrl+return', function(e){
		
		console.log(e)
	});





  $(".uploadbtn").click(function(e) {          
        e.preventDefault();
        $(".uploadtext").hide();
	$('div.step2').show("slide");

    });
           





                   
});




