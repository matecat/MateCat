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
    }).on('mousedown', '.splitArea .splitpoint', function(e) {
        e.preventDefault();
        e.stopPropagation();
        segment = $(this).parents('section');
        $(this).remove();
        UI.updateSplitNumber($(segment).find('.splitArea'));

        /*
                console.log('cliccato');
                segment = $(this).parents('section');
                console.log('a');
                console.log('prima: ', $('.splitArea').html());
                $(this).addClass('vediamo');
                $(this).remove();
                console.log('dopo: ', $('.splitArea').html());
                console.log('b');
                UI.updateSplitNumber($(segment).find('.splitArea'));
                console.log('c');
        */
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
            segment.find('.splitBar .buttons .cancel').click();
            newSegments = [];
            oldSid = segment.attr('id').split('-')[1];
            $.each(splittedSource, function (index) {
                segData = {
                    autopropagated_from: "0",
                    has_reference: "false",
                    parsed_time_to_edit: ["00", "00", "00", "00"],
                    readonly: "false",
                    segment: this.toString(),
                    segment_hash: segment.attr('data-hash'),
                    sid: oldSid + '-' + (index + 1),
                    status: "DRAFT",
                    time_to_edit: "0",
                    translation: "",
                    warning: "0"
                }
                newSegments.push(segData);
            });
            console.log('newSegments: ', newSegments);
            this.currentSegment.after(UI.renderSegments(newSegments));
            this.currentSegment.hide();
            this.gotoSegment(oldSid + '-1');
            this.setSegmentSplit(oldSid, splittedSource);
        },
        setSegmentSplit: function (sid, splittedSource) {
            splitAr = [];
            splitIndex = 0;
            console.log('splittedSource: ', splittedSource);
            $.each(splittedSource, function (index) {
//                console.log(UI.removeLockTagsFromString(this));
                console.log('prima: ', splittedSource[index]);
                cc = splittedSource[index].replace(/<span contenteditable=\"false\" class=\"locked(.*?)\"\>(.*?)<\/span\>/gi, "$2").replace(/"/gi, '&quot;');
                console.log('dopo: ', cc);
                ll = cc.length;
                splitIndex += ll;
                splitAr.push(splitIndex);
            })
            splitAr.pop();
            APP.doRequest({
                data: {
                    action:         "setSegmentSplit",
                    split_points:     splitAr.toString(),
                    id:             sid
                },
                error: function(d){
                    console.log('error');
                },
                success: function(d){
                    console.log('success');
                    if(d.data == 'OK') {

                    }
                }
            });
        },

        createSplitArea: function (segment) {
            source = $(segment).find('.source');
            source.after('<div class="splitBar"><div class="splitNum"><span class="num">1</span> segment<span class="plural"></span></div><div class="buttons"><a class="cancel" href="#">Cancel</a><a href="#" class="done">Done</a></div></div><div class="splitArea" contenteditable="true"></div>');
            splitArea = segment.find('.splitArea');
            splitArea.html(source.attr('data-original'));
            this.lockTags(splitArea);
            splitArea.find('.rangySelectionBoundary').remove();
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