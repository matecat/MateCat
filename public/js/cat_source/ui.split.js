/**
 * Component ui.split
 * Created by andreamartines on 11/03/15.
 */
if(config.splitSegmentEnabled) {
    UI.splittedTranslationPlaceholder = '##$_SPLIT$##';
    $('html').on('mouseover', '.editor .source, .editor .sid', function() {
        actions = $('.editor .sid').parent().find('.actions');
        actions.show();
    }).on('mouseout', '.sid, .editor:not(.split-action) .source, .editor:not(.split-action) .outersource .actions', function() {
        actions = $('.editor .sid').parent().find('.actions');
        actions.hide();
    }).on('click', 'body:not([data-offline-mode]) .outersource .actions .split:not(.cancel)', function(e) {
        e.preventDefault();
        segment = $(this).parents('section');
        $('.editor .split-shortcut').html('CTRL + W');
        console.log('split');
        UI.currentSegment.addClass('split-action');
        actions = $(this).parent().find('.actions');
        actions.show();
        UI.createSplitArea(segment);
    })

    .on('click', 'body:not([data-offline-mode]) .sid .actions .split', function(e) {
        e.preventDefault();
        $('.sid .actions .split').addClass('cancel');
        $('.split-shortcut').html('CTRL + W');
        UI.currentSegment.addClass('split-action');
        actions = $(this).parent().find('.actions');
        actions.show();
        segment = $(this).parents('section');
        UI.createSplitArea(segment);
    }).on('click', 'body[data-offline-mode] .sid .actions .split', function(e) {
        e.preventDefault();
    }).on('click', '.sid .actions .split.cancel', function(e) {
        e.preventDefault();
        $('.sid .actions .split').removeClass('cancel');
        source = $(segment).find('.source');
        $(source).removeAttr('style');
        UI.currentSegment.removeClass('split-action');
        $('.split-shortcut').html('CTRL + S');
        console.log('cancel');
        segment = $(this).parents('section');
        segment.find('.splitBar, .splitArea').remove();
        segment.find('.sid .actions').hide();
    }).on('keydown', '.splitArea', function(e) {
        console.log('keydown');
        e.preventDefault();
    }).on('keypress', '.splitArea', function(e) {
        console.log('keypress');
        e.preventDefault();
    }).on('keyup', '.splitArea', function(e) {
        console.log('keyup');
        e.preventDefault();
    }).on('click', '.splitArea', function(e) {
        e.preventDefault();
        if(window.getSelection().type == 'Range') return false;
        if($(this).hasClass('splitpoint')) return false;
        pasteHtmlAtCaret('<span class="splitpoint"><span class="splitpoint-delete"></span></span>');
        UI.cleanSplitPoints($(this));
        UI.updateSplitNumber($(this));
        $(this).blur();
    }).on('mousedown', '.splitArea .splitpoint', function(e) {
        e.preventDefault();
        e.stopPropagation();
        segment = $(this).parents('section');
        $(this).remove();
        UI.updateSplitNumber($(segment).find('.splitArea'));

      }).on('click', '.splitBar .buttons .done', function(e) {
        segment = $(this).parents('section');
        e.preventDefault();
        UI.splitSegment(segment);
    })

    $("html").bind('keydown', 'ctrl+s', function(e) {
        e.preventDefault();
        UI.currentSegment.find('.sid .actions .split').click();
    }).bind('keydown', 'ctrl+w', function(e) {
        e.preventDefault();
        UI.currentSegment.find('.sid .actions .split').click();
    })

    $.extend(UI, {
        splitSegment: function (segment) {
            ss = this.cleanSplittedSource(segment.find('.splitArea').html());
            splittedSource = ss.split('<span class="splitpoint"><span class="splitpoint-delete"></span></span>');
            segment.find('.splitBar .buttons .cancel').click();
            oldSid = segment.attr('id').split('-')[1];
            this.setSegmentSplit(oldSid, splittedSource);
        },
        cleanSplittedSource: function (str) {
            str = str.replace(/<span contenteditable=\"false\" class=\"locked(.*?)\"\>(.*?)<\/span\>/gi, "$2");
            str = str.replace(/<span class=\"currentSplittedSegment\">(.*?)<\/span>/gi, '$1');
            return str;
        },

        setSegmentSplit: function (sid, splittedSource) {
            splitAr = [0];
            splitIndex = 0;
            $.each(splittedSource, function (index) {
                cc = UI.cleanSplittedSource(splittedSource[index]);

                //SERVER NEEDS TEXT LENGTH COUNT ( WE MUST PAY ATTENTION TO THE TAGS ), so get html content as text
                //and perform the count
                ll = $('<div>').html(cc).text().length;

                //WARNING for the length count, must be done BEFORE encoding of quotes '"' to &quot;
                cc = cc.replace(/"/gi, '&quot;');

                splitIndex += ll;
                splitAr.push( splitIndex );
            });
            splitAr.pop();
            onlyOne = (splittedSource.length == 1)? true : false;
            splitArString = (splitAr.toString() == '0')? '' : splitAr.toString();

            // new version
            totalSource = '';
            $.each( splittedSource, function ( index ) {
                totalSource += $( document.createElement( 'div' ) ).html( splittedSource[index] ).text();
                if ( index < (splittedSource.length - 1) ) totalSource += UI.splittedTranslationPlaceholder;
            } );

            APP.doRequest({
                data: {
                    action:              "setSegmentSplit",
                    segment:            totalSource,
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
                    console.log('error');
                    var notification = {
                        title: 'Error',
                        text: d.errors[0].message,
                        type: 'error'
                    };
                    APP.addNotification(notification);
                    /*UI.showMessage({
                        msg: d.errors[0].message
                    });*/

                },
                success: function(d){
                    console.log('success');
                    if(d.errors.length) {
                        var notification = {
                            title: 'Error',
                            text: d.errors[0].message,
                            type: 'error'
                        };
                        APP.addNotification(notification);
                        /*UI.showMessage({
                            msg: d.errors[0].message
                        });*/
                    } else {
                        UI.setSegmentSplitSuccess(this);
                    }
                }
            });
        },
        setSegmentSplitSuccess: function (data) {
            oldSid = data.sid;
            console.log('oldSid: ', oldSid);
            splittedSource = data.splittedSource;
            console.log('splittedSource: ', splittedSource);
            splitAr = data.splitAr;
            newSegments = [];
            splitGroup = [];
            onlyOne = ( splittedSource.length == 1 ) ? true : false;

            //get all chunk translations, if this is a merge we want all concatenated targets
            //but we could reload the page? ( TODO, check if we can avoid spaces and special chars problem )
            translation = '';
            if( onlyOne ) {
                $( 'div[id*=segment-' + oldSid + ']' ).filter(function() {
                    return this.id.match(/-editarea/);
                } ).each( function( index, value ){
                    translation += $( value ).html();
                    //console.log( $( value ).html() );
                } );
            }

            $.each( splittedSource, function ( index ) {

                if( !onlyOne ) {
                    //there is a split, there are more than one source
                    translation = ( index == 0 ) ? UI.editarea.html() : '';
                }

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
                    translation: translation,
                    version: segment.attr('data-version'),
                    warning: "0"
                }
                newSegments.push(segData);
                splitGroup.push(oldSid + '-' + (index + 1));
            });
            oldSegment = $('#segment-' + oldSid);
            alreadySplitted = (oldSegment.length)? false : true;
            if(onlyOne) splitGroup = [];
            $('.test-invisible').remove();

            if(alreadySplitted) {
                prevSeg = $('#segment-' + oldSid + '-1').prev('section');

                if(prevSeg.length) {
                    $('section[data-split-original-id=' + oldSid + ']').remove();
                    $(prevSeg).after(UI.renderSegments(newSegments, true, splitAr, splitGroup));
                } else {
                    file = $('#segment-' + oldSid + '-1').parents('article');
                    $('section[data-split-original-id=' + oldSid + ']').remove();
                    $(file).prepend(UI.renderSegments(newSegments, true, splitAr, splitGroup));
                }
                if(splitGroup.length) {
                    $.each(splitGroup, function (index) {
                        UI.lockTags($('#segment-' + this + ' .source'));
                    });
                    this.gotoSegment(oldSid + '-1');
                } else {
                    UI.lockTags($('#segment-' + oldSid + ' .source'));
                    this.gotoSegment(oldSid);

                }
            } else {
                $(oldSegment).after(UI.renderSegments(newSegments, true, splitAr, splitGroup));
                $.each(splitGroup, function (index) {
                    UI.lockTags($('#segment-' + this + ' .source'));
                });
                $(oldSegment).remove();
                this.gotoSegment(oldSid + '-1');
            }

            $(document).trigger('split:segment:complete', oldSid);

        },

        createSplitArea: function (segment) {
            var splitAreaMarkup = '';
            var isSplitted = segment.attr('data-split-group') != '';
            var source = $(segment).find('.source');
            $(source).removeAttr('style');
            var targetHeight = $('.targetarea').height();
            segment.find('.splitContainer').remove();
            source.after('<div class="splitContainer"><div class="splitArea" contenteditable="true"></div><div class="splitBar"><div class="buttons"><a class="cancel hide" href="#">Cancel</a><a href="#" class="done btn-ok pull-right">Confirm</a></div><div class="splitNum pull-right">Split in <span class="num">1</span> segment<span class="plural"></span></div></div></div>');
            var splitArea = segment.find('.splitArea');
            setTimeout(function() {
                var sourceHeight = $(source).height();
                var splitAreaHeight = $(splitArea).height();
            if(sourceHeight >= splitAreaHeight) {
                    $('.splitBar').css('top', (sourceHeight + 70)+ 'px');
                    $(source).css('height', (sourceHeight)+ 'px');
                    $('.editor .wrap').css('padding-bottom', (splitAreaHeight - targetHeight)+ 'px');
            } else if(sourceHeight < splitAreaHeight) {
                    $(source).css('height', (splitAreaHeight + 100)+ 'px');
                    $('.splitBar').css('top', (splitAreaHeight + 70)+ 'px');
                }
            },100);

            if(isSplitted) {
                splitArea.removeAttr('style');
                var segments = segment.attr('data-split-group').split(',');
                var totalMarkup = '';
                $.each(segments, function (index) {
                    var newMarkup = $('#segment-' + this + ' .source').attr('data-original');
                    newMarkup = htmlDecode(newMarkup).replace(/&quot;/g, '\"');
                    if(this == UI.currentSegmentId) newMarkup = '<span class="currentSplittedSegment">' + newMarkup + '</span>';
                    totalMarkup += newMarkup;

                    if(index != segments.length - 1) totalMarkup += '<span class="splitpoint"><span class="splitpoint-delete"></span></span>';
                });
                splitAreaMarkup = totalMarkup;
            } else {
                splitAreaMarkup = source.attr('data-original');
                splitAreaMarkup = htmlDecode(splitAreaMarkup).replace(/&quot;/g, '\"');
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
        cleanSplitPoints: function (splitArea) {
            splitArea.html(splitArea.html().replace(/(<span class="splitpoint"><span class="splitpoint-delete"><\/span><\/span>)<span class="splitpoint"><span class="splitpoint-delete"><\/span><\/span>/gi, '$1'));
            splitArea.html(splitArea.html().replace(/(<span class="splitpoint"><span class="splitpoint-delete"><\/span><\/span>)$/gi, ''));
        }

    })
}
