/**
 * Component ui.split
 * Created by andreamartines on 11/03/15.
 */
if(config.splitSegmentEnabled) {
    $('html').on('click', '.sid .txt', function() {
        actions = $(this).parent().find('.actions');
        actions.toggle();
    }).on('click', '.sid .actions .split', function(e) {
        e.preventDefault();
        console.log('split');
        UI.createSplitArea($(this).parents('section'));
    })

    $.extend(UI, {
        createSplitArea: function (segment) {
            source = $(segment).find('.source');
            source.after('<div class="splitBar"><p>Click to add Split points</p><div class="buttons"><a href="#">Cancel</a><a href="#">Done</a></div></div><div class="splitArea" contenteditable="true"></div>');
//            console.log(segment.find('splitArea'));

            segment.find('.splitArea').html(source.attr('data-original'));
        },

    })
}