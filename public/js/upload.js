
$(document).ready(function(){
    APP.init();

    //set 
    if (!$('#source-lang option.custom').length) {
    //					$('#source-lang').val('en-US').attr('selected',true);
    $('#source-lang option.separator').remove();
    }
    if (!$('#target-lang option.custom').length) {
    //					$('#target-lang').val('fr-FR').attr('selected',true);
    $('#target-lang option.separator').remove();
    }

    if(!config.isLoggedIn) $('body').addClass('isAnonymous');

    $(".supported-file-formats").click(function(e){
        e.preventDefault();
        $(".supported-formats").show();
    });
    $(".close, .grayed").click(function(e){
        e.preventDefault();
        $(".grayed").fadeOut();
        $(".popup").fadeOut('fast');
    });
    $("#deselectMultilang").click(function(e){
        e.preventDefault();
        $('.listlang li.on input[type=checkbox]').click();
    });
    $("#swaplang").click(function(e){
        e.preventDefault();
        var src = $('#source-lang').val();
        var trg = $('#target-lang').val();
        if($('#target-lang').val().split(',').length > 1) {
            APP.alert({msg: 'Cannot swap languages when <br>multiple target languages are selected!'});
            return false;
        }
        $('#source-lang').val(trg);
        $('#target-lang').val(src);

        APP.changeTargetLang( src );

        if ( $('.template-download').length ) {
            if ( UI.conversionsAreToRestart() ) {
                APP.confirm({
                    msg: 'Source language changed. The files must be reimported.',
                    callback: 'confirmRestartConversions'
                });
            }
        } else if ( $('.template-gdrive').length ) {
            APP.confirm({
                msg: 'Source language has been changed.<br/>The files will be reimported.',
                callback: 'confirmGDriveRestartConversions'
            });
        }
    });
    $("#chooseMultilang").click(function(e){
        e.preventDefault();
        APP.closePopup();

//        $('.popup-languages .close').click();
        if ($('.popup-languages li.on').length) {
            var str = '';
            var vals = '';
            var direction ="ltr";
            $('.popup-languages li.on input').each(function(){
                str += $(this).parent().find('label').text() + ',';
                vals += $(this).val() + ',';
            })
            direction = UI.checkMultilangRTL();
            str = str.substring(0, str.length - 1);
            vals = vals.substring(0, vals.length - 1);
            var op = '<option id="extraTarget" selected="selected" data-direction="' + direction + '" value="' + vals + '">' + str + '</option>';
            $('#extraTarget').remove();
            if ($('#target-lang .separator').length) {
                ob = $('#target-lang .separator').next();
            } else {
                ob = $('#target-lang option').first();
            }
            ob.before(op);

            $('.translate-box.target h2 .extra').remove();
            $('.translate-box.target h2').append('<span class="extra">(' + $('.popup-languages li.on').length + ' languages)</span>');
            UI.checkRTL();
        }
    });
    $("#cancelMultilang").click(function(e){
        e.preventDefault();
        APP.closePopup();

//        $('.popup-languages .close').click();
        $('.popup-languages li.on').each(function(){
            $(this).removeClass('on').find('input').removeAttr('checked');
        });
    });

    $( 'a.authLink' ).click(function(e){
        e.preventDefault();

        $(".login-google").show();

        return false;
    });

    $('#sign-in').click(function(e){
        e.preventDefault();
        APP.googole_popup($(this).data('oauth'));
    })

    $('#dqf_key').on('paste', function(e){
        UI.checkDQFKey();
    }).on('keypress', function(e){
        e.preventDefault();
    }).on('focus', function(e){
        $(this).val('').removeClass('error valid');
    })
});



