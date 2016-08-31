/* global LXQ */
/*
 Component: lxq.main
 */

var LXQ = {

    enabled: function () {
        return !!config.lxq_enabled && !config.deny_lexiqa;
    },
    enable: function () {
        if (!config.lxq_enabled && !config.deny_lexiqa) {
            config.lxq_enabled = 1;
            // Todo Call Service to enable Tag Lexiqa
            var path = sprintf(
                '/api/v2/jobs/%s/%s/options',
                config.id_job, config.password
            );
            var data = {
                'lexiqa': true
            };
            $.ajax({
                url: path,
                type: 'POST',
                data : data
            }).done( function( data ) {
                if ($('#lexiqa-popup').hasClass('lxq-visible')) {
                    $('#lexiqabox').trigger('click');
                }
                $('#lexiqabox').removeAttr('style');
                if (!LXQ.initialized) {
                    LXQ.init();
}
                UI.render();
            });



        }
    },
    disable: function () {
        if (config.lxq_enabled) {
            config.lxq_enabled = 0;
            var path = sprintf(
                '/api/v2/jobs/%s/%s/options',
                config.id_job, config.password
            );
            var data = {
                'lexiqa': false
            };
            $.ajax({
                url: path,
                type: 'POST',
                data : data
            }).done( function( data ) {
                if ($('#lexiqa-popup').hasClass('lxq-visible')) {
                    $('#lexiqabox').trigger('click');
                }
                $('#lexiqabox').css('display', 'none');
                UI.render();
            });
        }
    },
    checkCanActivate: function () {
        if (_.isUndefined(this.canActivate)) {
            this.canActivate = config.lexiqa_languages.indexOf(config.source_rfc) > -1 &&
                config.lexiqa_languages.indexOf(config.target_rfc) > -1 &&
                !config.deny_lexiqa;
        }
        return this.canActivate;
    },
    const: {}
};

LXQ.init  = function () {
    LXQ.initialized = true;
    return (function ($, config, window, LXQ, undefined) {
        var partnerid = 'matecat';
        var colors = {
            numbers: '#D08053',
            punctuation: '#3AB45F',
            spaces: '#3AB45F',
            urls: '#b8a300',
            spelling: '#563d7c',
            specialchardetect: '#38C0C5',
            multiple: '#EA92B8',
            glossary: '#EA92B8',
            blacklist: '#EA92B8'
        };
        var warningMesasges = {
            u2: {t:	'email not found in source',
            	 s: 'email missing from target' },
            u1:	{t: 'url not found in source',
                 s: 'url missing from target'},
            n1:	{t: 'index not found in source',
				 s: 'index missing from target'},
            n2:	{t: 'number not found in source',
                 s: 'number missing from target'},
            n3:	{t: 'phonenumber not found in source',
                 s: 'phonenumber missing from target'},
            n4:	{t: 'date not found in source',
                 s: 'date missing from target'},                 
            s1:	{t: 'placeholder not found in source',
            	 s: 'placeholder missing from target'},
            p1:	{t: 'consecutive punctuation marks',
                 s: ''},					
            p2:	{t: 'space before punctuation mark',
                 s: ''},					
            p2sub1: {t: 'space before punctuation mark missing',
                   s: ''},
            p2sub2: {t: 'no space before opening parenthesis',
                   s: ''},	                   					
            p3: {t:	'space after punctuation mark missing',
                 s: ''},
            p3sub1: {t:	'no space after closing parenthesis',
                 s: ''},                 					
            p4:	{t: 'trailing punctuation mark different from source',
            	 s: 'trailing punctuation mark different from target'},
            l2:	{t: 'leading capitalization different from source',
                 s: 'leading capitalization different from target'},
            p6:	{t: 'should be capitalized after punctuation mark',
                 s: ''},										
            l1:	{t: 'repeated word',
                 s: ''},										
            c1:	{t: 'multiple spaces',
                 s: ''},										
            c2:	{t: 'segment starting with space',
                 s: ''},
            c2sub1: {t: 'space found after opening bracket/parenthesis',
                 s: ''},
            c3sub1: {t: 'space found before closing bracket/parenthesis',
                 s: ''},                 
            c3:	{t: 'space found at the end of segment',
                 s: ''},										
            s2:	{t: 'foreign character',
                 s: ''},					
            s3: {t: 'bracket mismatch',
                 s: ''},										
            s3sub1: {t: 'bracket not closed',
                 s: ''},										
            s3sub2: {t: 'bracket not opened',
                 s: ''},	
            s4: {t: 'character missing from source',
                 s: 'character missing from target'},	
            s5: {t: 'currency mismatch',
                s: 'currency mismatch'},                      
            default: {t:'not found in source',
            	      s: 'missing from target' }                 									 
        }
        
        var modulesNoHighlight = ['b1g','g1g','g2g','g3g'];
        var tpls = LXQ.const.tpls;

        var initConstants = function () {
            tpls = LXQ.const.tpls;
        }

        var renderHistoryWithErrors = function () {
            var root = $(tpls.historyHasErrors);

            // console.log('### warnings: ');
            // console.dir(UI.lexiqaData.lexiqaWarnings);
            UI.lexiqaData.segments.sort(function(a,b) {
                if (parseInt(a)>parseInt(b))
                    return 1;
                else if (parseInt(a)<parseInt(b))
                    return -1;
                else 
                //for splitted segments 111-1 111-2 etc.
                    if (a>b)
                        return 1;
                    else if (a<b)
                        return -1
                    else     
                        return 0;
            });
            // for (var j = 0; j < UI.lexiqaData.segments.length; j++) {
            //     var warnings = getVisibleWarningsCountForSegment(UI.lexiqaData.segments[j]);
            //     var ignores = getIgnoredWarningsCountForSegment(UI.lexiqaData.segments[j]);
            //     if (ignores!==0 || warnings!==0) {
            //         var segmentWarningsRow = $(tpls.segmentWarningsRow);
            //         segmentWarningsRow.find('.lxq-history-balloon-segment-number').text(UI.lexiqaData.segments[j]);
            //         segmentWarningsRow.find('.lxq-history-balloon-segment-link').attr('href', '#' + UI.lexiqaData.segments[j]);
            //         segmentWarningsRow.find('.lxq-history-balloon-total').text(warnings);
            //         segmentWarningsRow.find('.lxq-history-balloon-ignored').text(ignores);

            //         root.append(segmentWarningsRow);
            //     }
            //     else {
            //         console.log('renderHistoryWithErrors: not adding segment: '+ UI.lexiqaData.segments[j]);
            //     }
            // }

            // $('.lxq-history-balloon-has-comment').remove();
            // $('.lxq-history-balloon-has-no-comments').hide();

            // $('.lxq-history-balloon-outer').append(root);
        };
        
        var renderHistoryWithNoComments = function () {
            $('.lxq-history-balloon-has-comment').remove();
            $('.lxq-history-balloon-has-no-comments').show();
            //$('.lxq-comment-highlight-history').removeClass('lxq-visible');
        };
        var updateHistoryWithLoadedSegments = function () {
            if (UI.lexiqaData.segments.length == 0) {
                $('#lexiqabox')
                    .addClass('lxq-history-balloon-icon-has-no-comments')
                    .removeClass('lxq-history-balloon-icon-has-comment');
                renderHistoryWithNoComments();
            }
            else {
                $('#lexiqabox')
                    .removeClass('lxq-history-balloon-icon-has-no-comments')
                    .addClass('lxq-history-balloon-icon-has-comment');
                renderHistoryWithErrors();
            }
        };
        
        var refreshElements = function () {
            //initCommentLinks();
            //renderCommentIconLinks();
            updateHistoryWithLoadedSegments();
        };
        var hidePopUp = function () {
            if ($('#lexiqa-popup').hasClass('lxq-visible')) {
                $('#lexiqa-popup').removeClass('lxq-visible').focus();   
                //$('.cattool.editing').css('margin-top', 0);  
                $('#outer').css('margin-top', 20);      
                //LXQ.reloadPowertip();
            }
        };
        $(document).on('ready', function () {
            initConstants();
            //console.log('---------- lxq:ready')
            //$( '#lxq-history' ).remove();
            //$('.lxq-history-balloon-outer').remove();
            //$( '.header-menu li#filterSwitch' ).before( $( tpls.historyIcon ) );
            //$('.header-menu').append($(tpls.historyOuter).append($(tpls.historyNoComments)));


            refreshElements();            
            // XXX: there'a binding on 'section' are delegated to #outer in ui.events.js.
            //      Since our DOM elements are children of `section` we must attach to #outer
            //      too in order to prevent bubbling.
            //
            var delegate = '#outer';
            // $(delegate).on('click', function () {
            //     $('.lxq-history-balloon-outer').removeClass('lxq-visible');
            // });

        });


        $(document).on('lxq:ready', function (ev) {
            //console.log('---------- lxq:ready')
            //$( '#lxq-history' ).remove();
            $('.lxq-history-balloon-outer').remove();
            //$( '.header-menu li#filterSwitch' ).before( $( tpls.historyIcon ) );
            $('.header-menu').append($(tpls.historyOuter).append($(tpls.historyNoComments)));


            refreshElements();

            // open a comment if was asked by hash
            // var lastAsked = popLastCommentHash();
            // if ( lastAsked ) {
            //     openSegmentComment( UI.Segment.findEl( lastAsked.segmentId ) );
            // }
        });

            
        $(document).on('click', '#lexiqabox', function (ev) {
            ev.preventDefault();
            if ($('.searchbox').is(':visible')) {
                UI.toggleSearch(ev);
            }

            var lexiqaPopupHeight = $('#lexiqa-popup').height() + 30;

            $('#lexiqa-popup').toggleClass('lxq-visible').focus();

            if ($('#lexiqa-popup').hasClass('lxq-visible')) {
                $('#outer').css('margin-top', lexiqaPopupHeight);
                //go the first segment with errors...
                var segid = getFristSegmentWithWarning();
                if (UI.segmentIsLoaded(segid) === true)
                    UI.gotoSegment(segid);
                else {                    
                    config.last_opened_segment = segid;
                    window.location.hash = segid;
                    $('#outer').empty();
                    UI.render({
                        firstLoad: false
                    });
                }
            } else {
                $('#outer').css('margin-top', 20);
            }
            $('.mbc-history-balloon-outer').removeClass('mbc-visible');
        });
        var isNumeric = function (n) {
            return !isNaN(parseFloat(n)) && isFinite(n);
        }
        var strInsert = function (string, index, value) {
            return string.slice(0, index) + value + string.slice(index);
        };
        var cleanRanges = function (ranges,isSegmentCompleted) {
            var out = [];
            if ($.isPlainObject(ranges) || isNumeric(ranges[0])) {
                ranges = [ranges];
            }

            for (var i = 0, l = ranges.length; i < l; i++) {
                var range = ranges[i];

                if ($.isArray(range)) {
                    out.push({
                        color: color,
                        start: range[0],
                        end: range[1]
                    });
                }
                else {
                    if (range.ranges) {
                        if ($.isPlainObject(range.ranges) || isNumeric(range.ranges[0])) {
                            range.ranges = [range.ranges];
                        }

                        for (var j = 0, m = range.ranges.length; j < m; j++) {
                            if ($.isArray(range.ranges[j])) {
                                out.push({
                                    color: range.color,
                                    class: range.class,
                                    start: range.ranges[j][0],
                                    end: range.ranges[j][1]
                                });
                            }
                            else {
                                if (range.ranges[j].length) {
                                    range.ranges[j].end = range.ranges[j].start + range.ranges[j].length;
                                }
                                out.push(range.ranges[j]);
                            }
                        }
                    }
                    else {
                        if (range.length) {
                            range.end = range.start + range.length;
                        }
                        out.push(range);
                    }
                }
            }
            if (out.length==0) {
                return null;
            }
            out.sort(function (a, b) {
                if (a.end == b.end) {
                    return a.start - b.start;
                }
                return a.end - b.end;
            });
            var textMaxHighlight = out[out.length - 1].end;
            
            out.sort(function (a, b) {
                if (a.start == b.start) {
                    return a.end - b.end;
                }
                return a.start - b.start;
            });           
            var textMinHighlight = out[0].start;
            var txt = new Array(textMaxHighlight - textMinHighlight + 1);
            for (var i =0 ;i<txt.length;i++)
                txt[i]=[];
            $.each(out, function (j, range) {
                var i;
                if (range.ignore!=true) { //do not add the ignored errors
                //if (!(!isSegmentCompleted && (range.module=== 'c1'  || range.module=== 'c3'))) {
                //if (!(!isSegmentCompleted &&  range.module=== 'c3')) {
                if (!(range.module=== 'c3')) {                    
                    //if segment is not complete  - completely ignore doublespaces, becuase
                    //they seriously break formating....
                    for (i = range.start; i < range.end; i++) {
                        txt[i - textMinHighlight].push(j);
                    }
                }
                }
            });
            var newout = [];
            var curitem = null;
            for (var i = 0; i < txt.length; i++) {
                if (txt[i].length > 0) {
                    //more than one errors - start multiple
                    if (curitem == null) {
                        curitem = {};
                        curitem.start = i+textMinHighlight;
                        curitem.errors = txt[i].slice(0);
                        curitem.ignore = false;
                    }
                    else {
                        //check if the errors are the same or not..
                        var areErrorsSame = true;
                        if (curitem.errors.length == txt[i].length) {
                            for (var j=0;j<curitem.errors.length;j++) {
                                if (curitem.errors[j]!=txt[i][j]) {
                                    areErrorsSame = false;
                                    continue;
                                }
                            }
                        }
                        else {
                            areErrorsSame = false;
                        }
                        if (!areErrorsSame) {
                            curitem.end = i+textMinHighlight;
                            newout.push(curitem);
                            //restart it!
                            curitem = {};
                            curitem.start = i+textMinHighlight;
                            curitem.errors = txt[i].slice(0);                            
                        }
                    }
                }
                else {
                    if (curitem!=null) {
                        curitem.end = i+textMinHighlight;
                        newout.push(curitem);
                        curitem = null;
                    }
                }    
            }
            //console.dir(newout);
            /*
            var current = -1;
            $.each(out, function (i, range) {
                range.highlightstart = range.start;
                if (range.start >= range.end) {
                    $.error('Invalid range end/start');
                }
                if (range.start < current) {
                    //we need to do something here...
                    range.highlightstart = current;
                    
                    // $.error('Ranges overlap');
                }
                current = range.end;
            });
            out.reverse();
            */
            newout.reverse();
            return {out:out , newout:newout};
        };
        var cleanUpHighLighting = function (text) {
        //var cleanUpHighLighting = function (segment, isSource) {            
            //var regex = /<span id="lexiqahighlight".*?>(.+?)<\/span>/g;
            
            var regex = /<lxqwarning id="lexiqahighlight".*?>(.*?)<\/lxqwarning>/g;
            text = text.replace(regex, '$1');
            //clean up spurious highlighting that come when you select all text and delete...
            regex = /<span style="background-color:.*?>(.*)<\/span>/g;
            text =  text.replace(regex, '$1');
            regex = /<font color="#ff0000".*?>(.*)<\/font>/g;
            return text.replace(regex, '$1');
        }

        var highLightText = function (text, results,isSegmentCompleted,showHighlighting,isSource,segment) {
            //var text = $(area).val();
            var LTPLACEHOLDER = "##LESSTHAN##";
            var GTPLACEHOLDER = "##GREATERTHAN##";
            var rangesIn = [
                // {
                //     //color: '#EA92B8',
                //     ranges: results.multiple
                // }, 
                {
                    //color: '#D08053',
                    ranges: results.numbers
                }, {
                    //color: '#3AB45F',
                    ranges: results.punctuation
                }, {
                    //color: '#3AB45F',
                    ranges: results.spaces
                }, {
                    //color: '#38C0C5',
                    ranges: results.specialchardetect
                }, {
                    //color: '#b8a300',
                    ranges: results.urls
                }, {
                    //color: '#563d7c',
                    ranges: results.spelling
                }, {
                    //color: '#563d7c',
                    ranges: results.glossary
                }, {
                    //color: '#563d7c',
                    ranges: results.blacklist
                }];

            if (isSource)
                $.powerTip.destroy($('.tooltipas',segment));
            else 
                $.powerTip.destroy($('.tooltipa',segment));
            text = cleanUpHighLighting(text);
            
            var ranges = cleanRanges(rangesIn,isSegmentCompleted);
            if (ranges == null) {
                //do nothing
                return text;
            }        
            //console.log('-- text wo highlight: ' + text + '\nlength: ' + text.length);
            var totalSpaces = 0, nbspLoc = [], skip = false;
            for (var i = 0; i < text.length; i++) {
                if (!skip) {
                    if (text[i] === '<') {
                        skip = true;
                        continue;
                    }
                    if (text[i] === ' ')
                        totalSpaces++;
                    else if (text[i] === '&' ){
                        if (text.slice(i,i+6)==='&nbsp;') {
                            totalSpaces++;
                            nbspLoc.push(totalSpaces);
                        }
                    }
                }
                else {
                    if (text[i] === '>')
                        skip = false;
                }
            }
            var spcsBeforeRegex = /(( |\&nbsp;)+)<span id="selectionBoundary_/g;
            var spacesBefore = '';
            var match;
            while ((match = spcsBeforeRegex.exec(text))!==null) {
                if (match[1]!==undefined)
                    spacesBefore = match[1];
                break;
            }
            //console.log('spacesBefore: **'+spacesBefore+'**');
            text = text.replace(/\&nbsp;/g, ' ');
            text = text.replace(/\&amp;/g, '&');
            
            text = text.replace(/>/g, GTPLACEHOLDER).replace(/</g, LTPLACEHOLDER);
            text = text.replace(/\&gt;/g, '>').replace(/\&lt;/g, '<');
            
            //console.log('--text0: '+ text);
            //var findTags = /(##LESSTHAN##(\w+)\s.*?##GREATERTHAN##)(.*?)(##LESSTHAN##\/\2##GREATERTHAN##)|(##LESSTHAN##[\w]+\s.*?##GREATERTHAN##)/g;            
            //match 1: the tag type
            //match 2: the <aaaa asdfafd> part
            //match 3: the part betwen the <> and </>
            //match 4: the </aaa> part
            // *** match 5: means its a short of <xxx yyy zzz> tag
            
            var findTags=/(##LESSTHAN##(\w+)\s.*?##GREATERTHAN##)|(##LESSTHAN##\/(\w+)##GREATERTHAN##)|(##LESSTHAN##br\/?##GREATERTHAN##)/g;
            var match, tags = [];
            //match 1: the <aaaa asdfafd> part
            //match 2: the aaaa part of match 2
            //match 3: the </aaaa> part
            //match 4: the aaa part of match 3
            //var lastElement;
            while ((match = findTags.exec(text)) !== null) {               
                if (match[1] !== undefined) {
                    tags.push([match.index, match[1].length,0]);
                    //lastElement = {start:match.index,length:match[1].length,tag:match[2]};
                }
                else {
                    // var sub = match[3].length;
                    // if ((match[3] === '\uFEFF')||(match[3] ===LTPLACEHOLDER+'br'+GTPLACEHOLDER))
                    //     sub = 0;
                    if (match[4] === 'span' && text[match.index-1] === '\uFEFF') {
                            tags.push([match.index-1, match[3].length+1,0]);
                    }
                    else {
                        if (match[3]!==undefined) {
                        tags.push([match.index, match[3].length,0]);
                    }
                        else {
                            tags.push([match.index, match[5].length,0]);
                        }
                    }
                }
            }
            for (var k = 0; k < tags.length-1; k++) {
                if ((tags[k][0]+tags[k][1] == tags[k+1][0]))
                { //these tags are adjacent - join them
                    tags[k][1]+= tags[k+1][1];
                    tags[k][2]+= tags[k+1][2];
                    tags.splice(k+1,1);
                }            
            }            
            tags.forEach(function (tag) {
                ranges.newout.forEach(function (range) {
                    if (range.end > tag[0]) {
                        //range.start += tag[1];
                        range.end += tag[1];
                        range.start += tag[1];
                        //if (range.start >= tag[0] && range.start <tag[0]+tag[1]) {
                        if (range.start <tag[0]+tag[1]&& range.end >=tag[0]+tag[1]) {
                            //if our markup falls inside the span i.e. _<span>...</span>_ (_ = space)
                            //completelty ignore it...
                            range.ignore = true;
                            //console.log('MALALALA');
                        }
                        else {
                            range.start -= tag[2];
                            range.end -=tag[2];
                        }
                    }
                });
            });
            
            ranges.newout.forEach(function (range) {
                if (range.start != range.end ) {
                    if (range.start < text.length && !range.ignore) {
                        var data = '', multiple;
                        //calculate the color                        
                        if (range.errors.length == 1) {
                            range.color = ranges.out[range.errors[0]].color;
                            range.myClass = ranges.out[range.errors[0]].module + ' '+ranges.out[range.errors[0]].module[0]+'0'; //.p0 for instance
                            data = 'data-errors="'+ranges.out[range.errors[0]].errorid+'" ';
                        }
                        else {
                            range.myClass ='';
                            data = 'data-errors="';
                            multiple = 0;
                            range.errors.forEach(function (element){
                                range.myClass += ' '+ ranges.out[element].module + ' '+ranges.out[element].module[0]+'0';
                                data += ' '+ranges.out[element].errorid;
                                if (modulesNoHighlight.indexOf(ranges.out[element].module)<0)
                                    multiple++;
                            });
                            data += '" ';
                            range.color = colors.multiple;
                            if (multiple>1)
                                range.myClass += ' m';
                            range.myClass = range.myClass.trim();
                        }
                        
                        var mark = '##LESSTHAN##lxqwarning id="lexiqahighlight" '
                        //+' style="background-color:' + range.color + ';"'
                        + 'class="' +range.myClass;
                        //if (!showHighlighting) {
                        if (!UI.lexiqaData.enableHighlighting) {
                            mark+= ' lxq-invisible';
                            mark += '" ';
                        }
                        else {
                            if (isSource)
                                mark += ' tooltipas';
                            else
                                mark += ' tooltipa';
                            mark += '" '+data.trim()                                
                        }
                        mark +='##GREATERTHAN##';
                        if (range.breakEnd>0) {
                            text = strInsert(text, range.end, '##LESSTHAN##/lxqwarning##GREATERTHAN##');                                                       
                            text = strInsert(text, range.breakStart, mark);
                            text = strInsert(text, range.breakEnd, '##LESSTHAN##/lxqwarning##GREATERTHAN##');                                                       
                            text = strInsert(text, range.start, mark);                                                        
                        }
                        else {
                            text = strInsert(text, range.end, '##LESSTHAN##/lxqwarning##GREATERTHAN##');                           
                            text = strInsert(text, range.start, mark);
                        }
                    }
                }
            });
            //var regex = /(<)(?!\/?span)(.*?)(>)/g;
            //text = text.replace(regex, '&lt;$2&gt;');
            //console.log('-- text2: ' + text);
            // var indexOfmatch = text.indexOf(selectionmatch);
            // if (indexOfmatch>=0) {
            //     text  = text.slice(0,indexOfmatch)+ '&nbsp;'+ text.slice(indexOfmatch+1);
            // }
            // var lastspace = text.lastIndexOf(' ');
            // text  = text.slice(0,lastspace)+ '&nbsp;'+ text.slice(lastspace+1);
            // text =  text.replace(/\*\*\*SPACE\*\*\*/g, ' ');
            
            text = text.replace(/\&/g, '&amp;');
            
            text = text.replace(/>/g, '&gt;').replace(/</g, '&lt;');
            //console.log('-- text3: ' + text);
            text = text.replace(/##GREATERTHAN##/g, '>').replace(/##LESSTHAN##/g, '<');
            //console.log('-- text4: ' + text);
            // var indexOfmatch = text.indexOf('<span id="selectionBoundary_');
            // if (indexOfmatch>=0) {
            //     if (text[indexOfmatch-1] === ' ')
            //         text  = text.slice(0,indexOfmatch-1)+ '&nbsp;'+ text.slice(indexOfmatch);
            //     else {
            //         if (text.endsWith('</span>',indexOfmatch)) {
            //             console.log('span');
            //             if  (text[indexOfmatch-'</span>'.length-1]==' ') {
            //                 text = text.slice(0,indexOfmatch-'</span>'.length-1)+'</span>'
            //                     + '&nbsp;'+text.slice(indexOfmatch);
            //             }
            //         }
            //     }
            // }
            skip = false;
            var spcs2 = 0;
            for (var i = 0; i < text.length; i++) {
                if (!skip) {
                    if (text[i] === '<') {
                        skip = true;
                        continue;
                    }
                    if (text[i] === ' ')
                        spcs2++;
                    if (nbspLoc[0] == spcs2) {
                        nbspLoc.splice(0,1);
                        text = text.slice(0,i)+'&nbsp;'+text.slice(i+1);
                    }
                    else if (nbspLoc[0] === undefined)
                        break;
                }
                else {
                    if (text[i] === '>')
                        skip = false;
                }
            }
            spcsBeforeRegex.lastIndex = 0;
            text = text.replace(spcsBeforeRegex,spacesBefore+'<span id="selectionBoundary_');
            return text;
        }
        var toogleHighlighting = function () {
            var highlights = $('#outer').find('lxqwarning#lexiqahighlight');
            $.each(highlights, function(i, element) {
               $(element).toggleClass('lxq-invisible');
            });
            UI.lexiqaData.enableHighlighting = !UI.lexiqaData.enableHighlighting;
        }
        var toogleHighlightInSegment = function(segment) {
            var highlights = $(segment).find('lxqwarning#lexiqahighlight');
            $.each(highlights, function(i, element) {
               $(element).toggleClass('lxq-invisible');
            });
            var show = shouldHighlighWarningsForSegment(segment,!shouldHighlighWarningsForSegment(segment));

            postShowHighlight(UI.getSegmentId(segment),show);
        }
        var buildPowertipDataForSegment = function (segment) {
            var sourceHighlihts = $('.source', segment).find('lxqwarning#lexiqahighlight');
            var targetHighlihts = $('.editarea', segment).find('lxqwarning#lexiqahighlight');   
            
            $.each(sourceHighlihts, function(i, element) {
               var classlist = element.className.split(/\s+/);
               if ($(element).data('errors')!==undefined) {
               var errorlist = $(element).data('errors').trim().split(/\s+/);
               var root = $(tpls.lxqTooltipWrap);
               $.each(classlist, function(j,cl) {
                   var txt = getWarningForModule(cl,true);
                   if (cl === 'g3g') { 
                       //need to modify message with word.
                       var ind =  Math.floor(j/2); //we aredding the x0 classes after each class..
                       var word = UI.lexiqaData.lexiqaWarnings[UI.getSegmentId(segment)][errorlist[ind]].msg;
                       txt = txt.replace('#xxx#',word);
                   }
                   if (txt!==null) {
                       var row = $(tpls.lxqTooltipBody);
                       row.find('.tooltip-error-category').text(txt);
                       row.find('.tooltip-error-ignore').on('click', function(e) {
                           e.preventDefault();
                           LXQ.ignoreError(errorlist[j]);
                       });
                       root.append(row);
                   }
                    $(element).data('powertipjq', root);
               });
               }
            });
            $.each(targetHighlihts, function(i, element) {
               var classlist = element.className.split(/\s+/);
               if ($(element).data('errors')!==undefined) { //lxq-invisible elements do not have a data part
               var errorlist = $(element).data('errors').trim().split(/\s+/);  
               var root = $(tpls.lxqTooltipWrap);
                    var isSpelling = false, spellingRow = null, count = 0, word, ind;
               $.each(classlist,function(j,cl) {
                        isSpelling = false;
                   var txt = getWarningForModule(cl,false);
                   if (cl === 'g3g') {
                       //need to modify message with word.
                            var ind = Math.floor(j / 2); //we aredding the x0 classes after each class..
                            var word = UI.lexiqaData.lexiqaWarnings[UI.getSegmentId(segment)][errorlist[ind]].msg;
                       txt = txt.replace('#xxx#',word);
                   }
                        
                   if (txt!==null) {
                            count++;
                       var row = $(tpls.lxqTooltipBody);
                       row.find('.tooltip-error-category').text(txt);
                       row.find('.tooltip-error-ignore').on('click', function(e) {
                           e.preventDefault();
                            LXQ.ignoreError(errorlist[j]);
                       });
                            if (cl === 'd1g') {//spelling
                                isSpelling = true;
                                //element.text has the text
                                ind =  Math.floor(j/2); //we aredding the x0 classes after each class..
                                word = UI.lexiqaData.lexiqaWarnings[UI.getSegmentId(segment)][errorlist[ind]].msg;
                                row.find('.tooltip-error-category').addClass('spelling').data('word',word);
                                
                                spellingRow = row;
                            }
                            else
                       root.append(row);
                   }
               });
                if (spellingRow!==null && count == 1 ) //do not show on multiple errors...
                    root.append(spellingRow)
               $(element).data('powertipjq', root);        
               }    
            });                     
        }
        var reloadPowertip = function(segment) {
            if (segment!==undefined && segment!==null) {
                buildPowertipDataForSegment(segment);                
                $('.tooltipa',segment).powerTip({
                    placement: 'sw',
                    mouseOnToPopup: true,
                    smartPlacement: true,
                    closeDelay: 500
                });
                $('.tooltipas',segment).powerTip({
                    placement: 'se',
                    mouseOnToPopup: true,
                    smartPlacement: true,
                    closeDelay: 500
                });
                $('.tooltipa',segment).on('powerTipRender', function() {

                    //var rows = $('#powerTip').find('tooltip-error-category');
                    if ($(this).hasClass('d1g')) {
                    // make an ajax request
                        var word = $('#powerTip').find('.spelling').data('word');
                        var that = this;
                        $.ajax({
                            url: config.lexiqaServer+'/getSuggestions',
                            data: {
                                word: word,
                                lang: config.target_rfc
                            },
                            type: 'GET',
                            success: function(response) {
                                //$('#powerTip').html(response);
                                //var txt = getWarningForModule('d1g', false);
                                //var root = $(tpls.lxqTooltipWrap);
                                $.each(response,function(i,suggest) {
                                    //txt+='</br>'+suggest;
                                    var row = $(tpls.lxqTooltipSpellcheckBody);
                                    row.find('.tooltip-error-category').text(suggest);
                                    row.find('.tooltip-error-category').on('click', function (e) {
                                        e.preventDefault();
                                        LXQ.replaceWord(word, suggest,that);
                                    });
                                    $('#powerTip').append(row);
                                });
                            }
                        });
                    }
                });
            }
            else {
                $.powerTip.destroy($('.tooltipas'));
                $.powerTip.destroy($('.tooltipa'));
                $('.tooltipa').powerTip({
                    placement: 'sw',
                    mouseOnToPopup: true,
                    smartPlacement: true,
                    closeDelay: 500
                });
                $('.tooltipas').powerTip({
                    placement: 'se',
                    mouseOnToPopup: true,
                    smartPlacement: true,
                    closeDelay: 500
                });                
                $('.tooltipa').on('powerTipRender', function() {
                    //var rows = $('#powerTip').find('tooltip-error-category');
                    if ($(this).hasClass('d1g')) {
                    // make an ajax request
                        var word = $(this).text();
                        $.ajax({
                            url: config.lexiqaServer+'/getSuggestions',
                            data: {
                                word: word,
                                lang: config.target_rfc
                            },
                            type: 'GET',
                            success: function(response) {
                                //$('#powerTip').html(response);
                                //var txt = getWarningForModule('d1g', false);
                                //var root = $(tpls.lxqTooltipWrap);
                                $.each(response,function(i,suggest) {
                                    //txt+='</br>'+suggest;
                                    var row = $(tpls.lxqTooltipSpellcheckBody);
                                    row.find('.tooltip-error-category').text(suggest);
                                    row.find('.tooltip-error-category').on('click', function (e) {
                                        e.preventDefault();
                                        LXQ.replaceWord(word, suggest,that);
                                    });
                                    $('#powerTip').append(row);
                                });
                            }
                        });
                    }
                });              
            }
        }
            
        var replaceWord  = function(word, suggest,target) {
            if ($(target).closest('.editarea').attr('contenteditable')) {

                if ($(target).text() !== word) {
                    //there is no overlaping errors (like caps after punct...)
                    //there is an overlap. lets try to find everything manually..
                    var txt = $(target).text(),$el;
                    var startInWord = word.indexOf(txt);
                    var missingCharsStart=word.slice(0,startInWord);
                    var endInWord = startInWord+txt.length;
                    var missingCharsEnd = word.slice(endInWord);

                    while(missingCharsStart.length>0) {
                        $el = $(target).prev('lxqwarning');
                        txt = $el.text();
                        startInWord = missingCharsStart.indexOf(txt);
                        if (startInWord<0)
                            break;
                        missingCharsStart = missingCharsStart.slice(0,startInWord);
                        $el.remove();
                    } 
                    while(missingCharsEnd.length>0) {
                        $el = $(target).next('lxqwarning');
                        txt = $el.text();
                        startInWord = missingCharsEnd.indexOf(txt);
                        if (startInWord!==0) {
                            break;
                        }
                        missingCharsEnd = missingCharsEnd.slice(txt.length);
                        $el.remove();
                    } 
                }
                $.powerTip.hide();
                saveSelection();
                $(target).text(suggest);
                restoreSelection();
                $(target).addClass('lxq-invisible');
                UI.saveInUndoStack('lxq-replaceWord');
                UI.currentSegmentQA();
            }
        };
        var ignoreError = function(errorid) {
            var splits = errorid.split(/_/g);          
            var targetSeg = splits[1];
            var inSource = splits[splits.length-1] === 's' ? true: false;
            //console.log('ignoring error with id: '+ errorid +' in segment: '+targetSeg);
            UI.lexiqaData.lexiqaWarnings[targetSeg][errorid].ignored = true;
            $.powerTip.hide();
            redoHighlighting(targetSeg,inSource);
            refreshElements();
            if (getVisibleWarningsCountForSegment(targetSeg)<=0) {
                //remove the segment from database/reduce the number count
                UI.lxqRemoveSegmentFromWarningList(targetSeg);
            }  
            postIgnoreError(errorid);
        }
        
        var redoHighlighting = function(segmentId,insource) {
            var segment = UI.getSegmentById(segmentId);
            var highlights = {
                    source: {
                        numbers: [],
                        punctuation: [],
                        spaces: [],
                        urls: [],
                        spelling: [],
                        specialchardetect: [],
                        glossary: [],
                        blacklist: []
                    },
                    target: {
                        numbers: [],
                        punctuation: [],
                        spaces: [],
                        urls: [],
                        spelling: [],
                        specialchardetect: [],
                        glossary: [],
                        blacklist: []                               
                    }
            }; 
            $.each(UI.lexiqaData.lexiqaWarnings[segmentId],function(key,qadata) {
                if (qadata.insource) {
                    highlights.source[qadata.category].push(qadata);
                }
                else{
                    highlights.target[qadata.category].push(qadata);                                
                }           
            });
            var html = '';
            if (insource) {
                html = UI.clearMarks($.trim($(".source", segment).html()));
                html = highLightText(html,highlights.source,true,LXQ.shouldHighlighWarningsForSegment(segment),true,segment);
                // $(".source", segment).html(html);
                SegmentActions.replaceSourceText(UI.getSegmentId(segment), UI.getSegmentFileId(segment), html);
            }
            else {
                html = UI.clearMarks($.trim($(".editarea", segment).html()));
                html = highLightText(html,highlights.target,(segment===UI.currentSegment ? true : false),
                    LXQ.shouldHighlighWarningsForSegment(segment),false,segment);
                // $(".editarea", segment).html(html);
                SegmentActions.replaceEditAreaTextContent(UI.getSegmentId(segment), UI.getSegmentFileId(segment), html);
            }
            // $('.lxq-error-seg',segment).attr('numberoferrors',LXQ.getVisibleWarningsCountForSegment(segment));
            reloadPowertip(segment);
                       
        };
        
        var postShowHighlight = function(segmentid, show) {
            $.ajax({
                type: "POST",
                url: config.lexiqaServer+"/showhighlighting",
                data: {
                    data: {
                            segmentid: segmentid,
                            show: show
                            }
                },
                success:function(result){  
                    // console.log('postShowHighlight success: '+result);
                }
            });          
        }
        
        var postIgnoreError = function(errorid) {
            $.ajax({
                type: "POST",
                url: config.lexiqaServer+"/ignoreerror",
                data: {
                    data: {
                            errorid: errorid
                            }
                },
                success:function(result){  
                    // console.log('postIgnoreError success: '+result);
                }
            });          
        }
        
                
        var shouldHighlighWarningsForSegment = function (segId,value) {
            //var segId = UI.getSegmentId(seg);
            if (segId!==false){
            if (!UI.lexiqaData.segmentsInfo.hasOwnProperty(segId)) 
                UI.lexiqaData.segmentsInfo[segId]= {};
            if (!UI.lexiqaData.segmentsInfo[segId].hasOwnProperty('showHighlighting'))
                    UI.lexiqaData.segmentsInfo[segId].showHighlighting = true;  
            if (value!==undefined)
                UI.lexiqaData.segmentsInfo[segId].showHighlighting = value;
                
            return UI.lexiqaData.segmentsInfo[segId].showHighlighting ;
            }
            else{
                return false;
            }
        }
        var getVisibleWarningsCountForSegment = function(segment) {
            var segId ;
            if (typeof segment ==='string') {
                segId =segment; 
            }
            else
                segId = UI.getSegmentId(segment);
                
            if (!UI.lexiqaData.lexiqaWarnings.hasOwnProperty(segId))
                return 0;
            var count = 0;
            $.each(UI.lexiqaData.lexiqaWarnings[segId], function (i,element){
                if ((element.ignored === undefined || !element.ignored) && element.module!=='c3')
                    count++;
            });
            return count;
        }
        var getIgnoredWarningsCountForSegment =  function(segment) {
            var segId ;
            if (typeof segment ==='string') {
                segId =segment; 
            }
            else
                segId = UI.getSegmentId(segment);
                
            if (!UI.lexiqaData.lexiqaWarnings.hasOwnProperty(segId))
                return 0;
            var count = 0;
            $.each(UI.lexiqaData.lexiqaWarnings[segId], function (i,element){
                if (element.ignored === true)
                    count++;
            });
            return count;
        }
        var getWarningForModule = function (module,insource) {
            if (warningMesasges.hasOwnProperty(module))
                return (insource ? warningMesasges[module].s:warningMesasges[module].t);
            else
                return null;
 
        }
        var notCheckedSegments; //store the unchecked segments at startup        
        var doQAallSegments = function () {
            var segments = $('#outer').find('section');
            var notChecked = [];
            $.each(segments,function (keys,segment) {
                var segId = UI.getSegmentId(segment);
                if (UI.lexiqaData.segments.indexOf(segId) < 0) {
                    notChecked.push(segId);
                }
            });
            notCheckedSegments = notChecked;
            checkNextUncheckedSegment();
        }
        
        var checkNextUncheckedSegment = function (previousSegment) {
            if (previousSegment!==undefined && previousSegment!== null )
                reloadPowertip(previousSegment);
            if (!(notCheckedSegments.length >0))
                return;
            var segment = notCheckedSegments.pop();
            if (segment === undefined)
                return;
            var seg =  UI.getSegmentById(segment);
            if (UI.getSegmentTarget(seg).length > 0) {                

                UI.doLexiQA(seg, UI.getSegmentTarget(seg),segment, true, checkNextUncheckedSegment);
            }
            else {
                checkNextUncheckedSegment();
            } 
        }
        var getFristSegmentWithWarning = function () {
             if (UI.lexiqaData.hasOwnProperty('segments') && UI.lexiqaData.segments.length > 0) {
                return UI.lexiqaData.segments[0];
            }
            else {
                return UI.currentSegmentId;
            }
        }
        
        var getNextSegmentWithWarning = function () {
            //if there are no errors..
            if (!UI.lexiqaData.hasOwnProperty('segments') || UI.lexiqaData.segments.length == 0) 
                return UI.currentSegmentId;
            var ind  = -1;
            var segid_int = parseInt(UI.currentSegmentId);
            $.each(UI.lexiqaData.segments, function(i,el) {
                if (parseInt(el) > segid_int) {
                    ind = i;
                    return false; //this is a break for an $.each loop
                }
                if (parseInt(el) == segid_int) {
                    if (el>UI.currentSegmentId) {
                        //splitted segments : 111-1 111-2
                        ind = i;
                        return false;
                    }
                }
            });
            if (ind == -1) //this is the largest segmentid
                ind = 0;
            return UI.lexiqaData.segments[ind];
            
        }
        var getPreviousSegmentWithWarning = function () {
            //if there are no errors..
            if (!UI.lexiqaData.hasOwnProperty('segments') || UI.lexiqaData.segments.length == 0)
                return UI.currentSegmentId;
            var ind = -1;
            var segid_int = parseInt(UI.currentSegmentId);
            for (var i = UI.lexiqaData.segments.length - 1; i >= 0; i--) {
                if (parseInt(UI.lexiqaData.segments[i]) < segid_int) {
                    ind = i;
                    break;
                }
                if (parseInt(UI.lexiqaData.segments[i]) == segid_int) {
                    if (UI.lexiqaData.segments[i]<UI.currentSegmentId) {
                        //splitted segments : 111-1 111-2
                        ind = i;
                        break;
                    }
                }
            }
            if (ind == -1) //this is the largest segmentid
                ind = UI.lexiqaData.segments.length - 1;
            return UI.lexiqaData.segments[ind];
        }
        
        var initPopup = function () {
            
            $.ajax(
                {
                    type: "GET",
                    url: config.lexiqaServer+"/tooltipwarnings",            
                    success:function(result){
                        warningMesasges = result;
                        modulesNoHighlight = []
                        $.each(result, function(key,el) {
                            if (key[key.length-1] === 'g')
                                modulesNoHighlight.push(key);   
                        });
                    }
                    ,error:function(result){
                        // console.err(result);
                    }
            });
           
            $('#lexiqa-quide-link').attr('href', config.lexiqaServer + '/documentation.html');
            $('#lexiqa-report-link').attr('href', config.lexiqaServer + '/errorreport?id='+this.partnerid+'-' + config.id_job + '-' + config.password+'&type='+(config.isReview?'revise':'translate'));

            $('#lexiqa-prev-seg').on('click', function (e) {
                e.preventDefault();
                var segid = getPreviousSegmentWithWarning();
                if (UI.segmentIsLoaded(segid) === true)
                    UI.gotoSegment(segid);
                else {
                    config.last_opened_segment = segid;
                    //config.last_opened_segment = this.nextUntranslatedSegmentId;
                    window.location.hash = segid;
                    $('#outer').empty();
                    UI.render({
                        firstLoad: false
                    });
                }
            });
            $('#lexiqa-next-seg').on('click', function (e) {
                e.preventDefault();
                //UI.gotoSegment(getNextSegmentWithWarning());
                var segid = getNextSegmentWithWarning();
                if (UI.segmentIsLoaded(segid) === true)
                    UI.gotoSegment(segid);
                else {                    
                    //UI.reloadWarning();
                    config.last_opened_segment = segid;
                    //config.last_opened_segment = this.nextUntranslatedSegmentId;
                    window.location.hash = segid;
                    $('#outer').empty();
                    UI.render({
                        firstLoad: false
                    });                    
                }
            });
        }
        // Interfaces
        $.extend(LXQ, {
            refreshElements: refreshElements,
            highLightText: highLightText,
            cleanUpHighLighting: cleanUpHighLighting,
            colors: colors,
            toogleHighlightInSegment: toogleHighlightInSegment,
            toogleHighlighting:toogleHighlighting,
            shouldHighlighWarningsForSegment: shouldHighlighWarningsForSegment,
            getVisibleWarningsCountForSegment:getVisibleWarningsCountForSegment,
            getIgnoredWarningsCountForSegment:getIgnoredWarningsCountForSegment,
            buildPowertipDataForSegment: buildPowertipDataForSegment,
            reloadPowertip : reloadPowertip,
            ignoreError: ignoreError,
            redoHighlighting:redoHighlighting,
            doQAallSegments: doQAallSegments,
            getNextSegmentWithWarning: getNextSegmentWithWarning,
            getPreviousSegmentWithWarning:getPreviousSegmentWithWarning,
            initPopup: initPopup,
            hidePopUp: hidePopUp,
            partnerid: partnerid,
            getWarningForModule: getWarningForModule,
            replaceWord: replaceWord
        });

    })(jQuery, config, window, LXQ);
};

if (LXQ.enabled()) {
    LXQ.init();
}

