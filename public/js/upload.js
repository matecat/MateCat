
$(document).ready(function(){

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
        var src = $('#source-lang').dropdown('get value');
        var trg = $('#target-lang').dropdown('get value');
        if(trg.split(',').length > 1) {
            APP.alert({msg: 'Cannot swap languages when <br>multiple target languages are selected!'});
            return false;
        }
        $('#source-lang').dropdown('set selected', trg);
        $('#target-lang').dropdown('set selected', src);

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
            var op = '<div id="extraTarget" class="item" data-selected="selected" data-direction="' + direction + '" data-value="' + vals + '">' + str + '</div>';
            $('#extraTarget').remove();
            $('#target-lang div.item').first().before(op);
            setTimeout(function () {
                $('#target-lang').dropdown('set selected', vals);
            });


            $('.translate-box.target h2 .extra').remove();
            $('.translate-box.target h2').append('<span class="extra">(' + $('.popup-languages li.on').length + ' languages)</span>');
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
});


