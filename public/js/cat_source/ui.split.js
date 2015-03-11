/**
 * Component ui.split
 * Created by andreamartines on 11/03/15.
 */
if(config.splitSegmentEnabled) {
    $('html').on('mouseover', '.sid', function() {
        actions = $(this).parent().find('.actions');
        actions.show();
    }).on('mouseout', '.sid', function() {
        actions = $(this).parent().find('.actions');
        actions.hide();
    }).on('click', '.sid .actions .split', function(e) {
        e.preventDefault();
        console.log('split');
        UI.createSplitArea($(this).parents('section'));
    }).on('keydown', '.splitArea', function(e) {
        e.preventDefault();
    }).on('click', '.splitArea', function(e) {
        if($(this).hasClass('splitpoint')) return false;
        pasteHtmlAtCaret('<span class="splitpoint"></span>');
        UI.updateSplitNumber($(this));
    }).on('click', '.splitArea .splitpoint', function() {
        segment = $(this).parents('section');
        $(this).remove();
        UI.updateSplitNumber($(segment).find('.splitArea'));
    }).on('click', '.splitBar .buttons .cancel', function(e) {
        e.preventDefault();
        segment = $(this).parents('section');
        segment.find('.splitBar, .splitArea').remove();
        segment.find('.sid .actions').hide();
    }).on('click', '.splitBar .buttons .done', function(e) {
        segment = $(this).parents('section');
        e.preventDefault();
        UI.splitSegment(segment);
    })

    $.extend(UI, {
        splitSegment: function (segment) {
            splittedSource = segment.find('.splitArea').html().split('<span class="splitpoint"></span>');
            numSplit = splittedSource.length;
            console.log('numSplit: ', numSplit);

        },
        createSplitArea: function (segment) {
            source = $(segment).find('.source');
            source.after('<div class="splitBar"><div class="splitNum"><span class="num">1</span> segment<span class="plural"></span></div><div class="buttons"><a class="cancel" href="#">Cancel</a><a href="#" class="done">Done</a></div></div><div class="splitArea" contenteditable="true"></div>');
//            console.log(segment.find('splitArea'));

            segment.find('.splitArea').html(source.attr('data-original'));
        },
        updateSplitNumber: function (area) {
            segment = $(area).parents('section');
            numSplits = $(area).find('.splitpoint').length + 1;
            splitnum = $(segment).find('.splitNum');
            $(splitnum).find('.num').text(numSplits);
            if (numSplits > 1) {
                $(splitnum).find('.plural').text('s');
                splitnum.show();
            } else {
                $(splitnum).find('.plural').text('');
                splitnum.hide();
            }
        },

    })
}