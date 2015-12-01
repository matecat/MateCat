$(document).ready(function() {
    APP.fitText($('body'), $('#pname'), 30, 33);

    var newHref = $('.downloadtr-button').attr('href').replace('/translate/', '').replace('/revise/', '');
    var comingFrom = $.cookie('comingFrom');
    $('.downloadtr-button').attr('href', '/' + comingFrom + '/' + newHref);
    if(comingFrom == 'revise') $('.downloadtr-button').text("< Back to Revision");
});


