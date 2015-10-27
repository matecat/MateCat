$(document).ready(function(){
	//logout link
	$('#logoutlink').on('click',function(event){
			//stop form submit
			event.preventDefault();
			$.post('/ajaxLogout',{logout:1},function(data){
				if('unlogged'==data){
					//ok, unlogged
					window.location.reload();
				}
			})
	})
});
