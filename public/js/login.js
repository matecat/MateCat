
function gopopup(url) {
	//var rid=$('#rid').text();
	//url=url+'&rid='+rid;
	var newWindow = window.open(url, 'name', 'height=600,width=900');
	if (window.focus) {
		newWindow.focus();
	}
}

function errorMsgReset(){
	/*if($('#msg').text().length > 0)*/ $('#msg').text('');
}
$(document).ready(function(){
    //login functions
    $('#logintranslated').submit(function(event){
        //stop form submit
        event.preventDefault();
        //show spinner
        $('#loadingspinner').css('visibility','visible');
        //disable button
        $('#clientiSubmit').prop('disabled',true);
        $('#clientiSubmit').addClass('disabled');
        //get data
        var uid=$('#clientiUserid').val();
        var psw=$('#clientiPassword').val();
        //submit
        $.post('/ajaxLogin',{login:uid, pass:psw },function(data){
            //var data=jQuery.parseJSON(data);
            //check outcome
            if('logged'==data){
            //ok, authenticated
            //pull out from DOM the url to go to
            var incomingUrl=$('#incomingurl').text();
            //console.log(incomingUrl);
            //redirect
            window.location=incomingUrl;
            }else{//fail
            //hide spinner
            $('#loadingspinner').css('visibility','hidden');
            //show error
            $('#msg').text('Login failed: wrong user or password');
            //enable button
            $('#clientiSubmit').prop('disabled',false);
            $('#clientiSubmit').removeClass('disabled');
            }
            return false;
            })
    })


    //hide error messge when retrying
    $('#clientiUserid').on('keypress',function(){
        errorMsgReset()
    })

    $('#clientiPassword').on('keypress',function(){
        errorMsgReset()
    })

    $('#emailToReset').on('keypress',function(){
        errorMsgReset()
    })

    $('#sign-in').click(function(e){
        e.preventDefault();
        gopopup($(this).data('oauth'));
    })

    $('#formReset').submit(function(event){
        //stop form submit
        event.preventDefault();
        //show spinner
        $('#loadingspinner').css('visibility','visible');
        //disable button
        $('#passSubmit').prop('disabled',true);
        $('#passSubmit').addClass('disabled');
        //get data
        var uid=$('#emailToReset').val();
        //submit
        $.post('/ajaxLogin',{login:uid,reset:1},function(data){
            //var data=jQuery.parseJSON(data);
            //check outcome
            if('sent'==data){
            //ok, authenticated
            //hide spinner
            $('#loadingspinner').css('visibility','hidden');
            //show error
            $('#msg').text('Mail sent');
            //enable button
            $('#passSubmit').prop('disabled',false);
            $('#passSubmit').removeClass('disabled');
            }else{//fail
            //hide spinner
            $('#loadingspinner').css('visibility','hidden');
            //show error
            $('#msg').text('Reset failed: wrong user or password');
            //enable button
            $('#passSubmit').prop('disabled',false);
            $('#passSubmit').removeClass('disabled');
            }
            return false;
        })
    })
})
