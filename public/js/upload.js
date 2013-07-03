$(document).ready(function(){

    //set 
    if (!$('#source-lang option.custom').length) {
    //					$('#source-lang').val('en-US').attr('selected',true);
    $('#source-lang option.separator').remove();
    }
    if (!$('#target-lang option.custom').length) {
    //					$('#target-lang').val('fr-FR').attr('selected',true);
    $('#target-lang option.separator').remove();
    }

    $(".supported-file-formats").click(function(e){
        e.preventDefault();
        $(".grayed").fadeIn();
        $(".supported-formats").fadeIn('fast');
    });
    $(".close, .grayed").click(function(e){
        e.preventDefault();
        $(".grayed").fadeOut();
        $(".popup").fadeOut('fast');
    });
    $("#chooseMultilang").click(function(e){
        $('.popup-languages .close').click();
        if ($('.popup-languages li.on').length) {
            console.log('ecco');
            var str = '';
            var vals = '';
            $('.popup-languages li.on input').each(function(){
                str += $(this).parent().find('label').text() + ',';
                vals += $(this).val() + ',';
            })
            str = str.substring(0, str.length - 1);
            vals = vals.substring(0, vals.length - 1);
            var op = '<option id="extraTarget" selected="selected" value="' + vals + '">' + str + '</option>';
            $('#extraTarget').remove();
            if ($('#target-lang .separator').length) {
                ob = $('#target-lang .separator').next();
            } else {
                ob = $('#target-lang option').first();
            }
            ob.before(op);

            $('.translate-box.target h2 .extra').remove();
            $('.translate-box.target h2').append('<span class="extra">(' + $('.popup-languages li.on').length + ' languages)</span>');
        }
    });
    $("#cancelMultilang").click(function(e){
        $('.popup-languages .close').click();
        $('.popup-languages li.on').each(function(){
            $(this).removeClass('on').find('input').removeAttr('checked');
        });
    });
    if ($.browser.mozilla) {
        $('.popup-languages .popup-box').empty().append('<ul class="test"><li>test</li><li>test</li><li>test</li><li>test</li><li>test</li><li>test</li><li>test</li><li>test</li><li>test</li><li>test</li></ul>');
    };

});


