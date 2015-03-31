/**
 * Component ui.split
 * Created by andreamartines on 11/03/15.
 */
if(config.splitSegmentEnabled) {
    $('html').on('mouseover', '.editor .source, .editor .sid', function() {
        actions = $('.editor .sid').parent().find('.actions');
        actions.show();
    }).on('mouseout', '.sid, .editor:not(.split-action) .source, .editor:not(.split-action) .outersource .actions', function() {
        actions = $('.editor .sid').parent().find('.actions');
        actions.hide();
    }).on('click', '.outersource .actions .split:not(.cancel)', function(e) {
        e.preventDefault();
        segment = $(this).parents('section');
//        $('.editor .outersource .actions .split').addClass('cancel');
        $('.editor .split-shortcut').html('CTRL + W');
        console.log('split');
        UI.currentSegment.addClass('split-action');
        actions = $(this).parent().find('.actions');
        actions.show();
        UI.createSplitArea(segment);
    }).on('click', '.outersource .actions .split.cancel', function(e) {
        e.preventDefault();
        console.log('cancel');
        $('.editor .outersource .actions .split').removeClass('cancel');
        segment = $(this).parents('section');
        UI.currentSegment.removeClass('split-action');
        $('.editor .split-shortcut').html('CTRL + S');
        segment.find('.splitBar, .splitArea').remove();
//        segment.find('.sid .actions').hide();

    }).on('click', '.sid .actions .split', function(e) {
        e.preventDefault();
        $('.sid .actions .split').addClass('cancel');
        $('.split-shortcut').html('CTRL + W');
//        console.log('split');
        UI.currentSegment.addClass('split-action');
        actions = $(this).parent().find('.actions');
        actions.show();
        UI.createSplitArea($(this).parents('section'));

    }).on('click', '.sid .actions .split.cancel', function(e) {
        e.preventDefault();
        $('.sid .actions .split').removeClass('cancel');
        segment = $(this).parents('section');
        UI.currentSegment.removeClass('split-action');
        $('.split-shortcut').html('CTRL + S');
        segment.find('.splitBar, .splitArea').remove();
        segment.find('.sid .actions').hide();
    }).on('keydown', '.splitArea', function(e) {
        e.preventDefault();
    }).on('click', '.splitArea', function(e) {
        if($(this).hasClass('splitpoint')) return false;
        pasteHtmlAtCaret('<span class="splitpoint"><span class="splitpoint-delete"></span></span>');
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
        UI.currentSegment.removeClass('split-action');
        $('.split-shortcut').html('CTRL + S');
        segment.find('.splitBar, .splitArea').remove();
        segment.find('.sid .actions').hide();
    }).on('click', 'segment:not(.editor)', function(e) {
        $('.splitBar .buttons .cancel').click();
    }).on('click', '.splitBar .buttons .done', function(e) {
        segment = $(this).parents('section');
        e.preventDefault();
        UI.splitSegment(segment);

        /*
                alreadySplitted = segment.attr('data-split-group') != '';
                if(alreadySplitted) {

                } else {
                    UI.splitSegment(segment);
                }
        */
    })

    $.extend(UI, {
        splitSegment: function (segment) {
            splittedSource = segment.find('.splitArea').html().split('<span class="splitpoint"><span class="splitpoint-delete"></span></span>');
            segment.find('.splitBar .buttons .cancel').click();
            oldSid = segment.attr('id').split('-')[1];
            this.setSegmentSplit(oldSid, splittedSource);
        },
        setSegmentSplit: function (sid, splittedSource) {
            splitAr = [0];
            splitIndex = 0;
//            console.log('splittedSource: ', splittedSource);
            $.each(splittedSource, function (index) {
//                console.log(UI.removeLockTagsFromString(this));
//                console.log('prima: ', splittedSource[index]);
                cc = splittedSource[index].replace(/<span contenteditable=\"false\" class=\"locked(.*?)\"\>(.*?)<\/span\>/gi, "$2");

                //SERVER NEEDS TEXT LENGTH COUNT ( WE MUST PAY ATTENTION TO THE TAGS ), so get html content as text
                //and perform the count
                ll = $('<div>').html(cc).text().length;

                //WARNING for the length count, must be done BEFORE encoding of quotes '"' to &quot;
                cc = cc.replace(/"/gi, '&quot;');

//                console.log('dopo: ', cc);
                splitIndex += ll;
                splitAr.push( splitIndex );
            });
            splitAr.pop();
            onlyOne = (splittedSource.length == 1)? true : false;
            splitArString = (splitAr.toString() == '0')? '' : splitAr.toString();

            APP.doRequest({
                data: {
                    action:              "setSegmentSplit",
                    split_points_source: '[' + splitArString + ']',
                    id_segment:          sid,
                    id_job:              config.job_id,
                    password:            config.password
                },
                context: {
                    splittedSource: splittedSource,
                    sid: sid,
                    splitAr: splitAr
                },
                error: function(d){
                    // temp
                    UI.setSegmentSplitSuccess(this);
                    console.log('error');
                },
                success: function(d){
                    UI.setSegmentSplitSuccess(this);
                    console.log('success');
                    if(d.data == 'OK') {

                    }
                }
            });
        },
        setSegmentSplitSuccess: function (data) {
            oldSid = data.sid;
//            console.log('oldSid: ', oldSid);
            splittedSource = data.splittedSource;
            splitAr = data.splitAr;
            newSegments = [];
            splitGroup = [];
            onlyOne = (splittedSource.length == 1)? true : false;
            $.each(splittedSource, function (index) {
                segData = {
                    autopropagated_from: "0",
                    has_reference: "false",
                    parsed_time_to_edit: ["00", "00", "00", "00"],
                    readonly: "false",
                    segment: this.toString(),
                    segment_hash: segment.attr('data-hash'),
                    sid: (onlyOne)? oldSid : oldSid + '-' + (index + 1),
                    split_points_source: [],
                    status: "DRAFT",
                    time_to_edit: "0",
                    translation: "",
                    warning: "0"
                }
                newSegments.push(segData);
                splitGroup.push(oldSid + '-' + (index + 1));
            });
            oldSegment = $('#segment-' + oldSid);
            alreadySplitted = (oldSegment.length)? false : true;
            if(onlyOne) splitGroup = [];
            if(alreadySplitted) {
                prevSeg = $('#segment-' + oldSid + '-1').prev('section');
                if(prevSeg.length) {
                    $('section[data-split-original-id=' + oldSid + ']').remove();
                    /*
                    $.each(splitGroup, function (index) {
                        $('#segment-' + this).remove();
                    });
                    */
                    $(prevSeg).after(UI.renderSegments(newSegments, true, splitAr, splitGroup));
                    if(splitGroup.length) {
                        console.log('dovrebbe esser qui');
                        console.log('oldSid: ', oldSid);
                        $.each(splitGroup, function (index) {
                            UI.lockTags($('#segment-' + this + ' .source'));
                        });
                        this.gotoSegment(oldSid + '-1');
                    } else {
                        console.log('o qui');
                        console.log('oldSid: ', oldSid);
                        UI.lockTags($('#segment-' + oldSid + ' .source'));
                        this.gotoSegment(oldSid);

                    }

                } else {

                }
            } else {
                $(oldSegment).after(UI.renderSegments(newSegments, true, splitAr, splitGroup));
                $.each(splitGroup, function (index) {
                    UI.lockTags($('#segment-' + this + ' .source'));
                });
                $(oldSegment).remove();
                this.gotoSegment(oldSid + '-1');
            }

//            console.log('or ID: ', UI.currentSegment.attr('data-split-original-id'));
//            console.log('oldSegment: ', oldSegment);
//            $("section[data-split-original-id=" + UI.currentSegment.attr('data-split-original-id') + "]").remove();
        },

        createSplitArea: function (segment) {
            isSplitted = segment.attr('data-split-group') != '';
            source = $(segment).find('.source');
            source.after('<div class="splitArea" contenteditable="true" style="height: ' + $(source).height() + 'px"></div><div class="splitBar"><div class="buttons"><a class="cancel hide" href="#">Cancel</a><a href="#" class="done btn-ok pull-right">Confirm</a></div><div class="splitNum pull-right">Split in <span class="num">1</span> segment<span class="plural"></span></div></div>');
            splitArea = segment.find('.splitArea');
            if(isSplitted) splitArea.removeAttr('style');
            if(isSplitted) {
                console.log('ecco: ', '');
                segments = segment.attr('data-split-group').split(',');
                totalMarkup = '';
                $.each(segments, function (index) {
                    totalMarkup += $('#segment-' + this + ' .source').attr('data-original');
                    if(index != segments.length - 1) totalMarkup += '<span class="splitpoint"><span class="splitpoint-delete"></span></span>';
                });
                splitAreaMarkup = totalMarkup;
            } else {
                splitAreaMarkup = source.attr('data-original');
            }
            splitArea.html(splitAreaMarkup);
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