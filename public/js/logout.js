$(document).ready(function(){
	//logout link
	$('#logoutlink').on('click',function(event){
			//stop form submit
			event.preventDefault();
			$.post('/ajaxLogout',{logout:1},function(data){
				if('unlogged'==data){
					//ok, unlogged
					if($('body').hasClass('manage')) {
						location.href = config.hostpath + config.basepath;
					} else {
						window.location.reload();
					}
				}
			})
	});

	$('.user-menu-preferences').on('click', function () {
		$('#login-modal').css('display', 'block');
	});
	$('.close-login').on('click', function () {
		$('#login-modal').css('display', 'none');
	});
	window.onclick = function(event) {
		if (event.target == document.getElementById('#login-modal')) {
			$('#login-modal').css('display', 'none');
		}
	}
});
