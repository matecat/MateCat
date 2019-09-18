

function focusOnPlaceholder() {
	var placeholder = document.getElementById('placeHolder');
	if (!placeholder)
		return;
	var sel, range;

	if (window.getSelection && document.createRange) {
		range = document.createRange();
		range.selectNodeContents(placeholder);
		range.collapse(true);
		sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange(range);
	} else if (document.body.createTextRange) {
		range = document.body.createTextRange();
		range.moveToElementText(placeholder);
		range.select();
	}
    placeholder.remove();
}

function truncate_filename(n, len) {
	var ext = n.substring(n.lastIndexOf(".") + 1, n.length).toLowerCase();
	var filename = n.replace('.' + ext, '');
	if (filename.length <= len) {
		return n;
	}
	filename = filename.substr(0, len) + (n.length > len ? '[...]' : '');
	return filename + '.' + ext;
}

function insertNodeAtCursor(node) {
    try {
        var range, html;
        if (window.getSelection && window.getSelection().getRangeAt) {
            if ((window.getSelection().type == 'Caret') || (UI.isFirefox)) {
                range = window.getSelection().getRangeAt(0);
                range.insertNode(node);
                setCursorAfterNode(range, node);
            }
        } else if (document.selection && document.selection.createRange) {
            range = document.selection.createRange();
            html = (node.nodeType == 3) ? node.data : node.outerHTML;
            range.pasteHTML(html);
        }
    } catch (e) {
        console.error("Fail to insert node at cursor", e);
    }
}

function insertTextAtCursor(text) {
    var sel, range, html;
    if (window.getSelection) {
        sel = window.getSelection();
        if (sel.getRangeAt && sel.rangeCount) {
            range = sel.getRangeAt(0);
            range.deleteContents();
            range.insertNode( document.createTextNode(text) );
        }
    } else if (document.selection && document.selection.createRange) {
        document.selection.createRange().text = text;
    }
}

function setCursorAfterNode(range, node) {
	range.setStartAfter(node);
	range.setEndAfter(node);
	window.getSelection().removeAllRanges();
	window.getSelection().addRange(range);
}

function __ignoreSelection( range ) {
	if (
		range.startContainer == range.endContainer &&
		range.startContainer == document
	) {
		return true ;
	}
}

function pasteHtmlAtCaret(html, selectPastedContent) {
    var sel, range;

    if (window.getSelection) {
        // IE9 and non-IE
        sel = window.getSelection();

        if (sel.getRangeAt && sel.rangeCount) {
            range = sel.getRangeAt(0);

			if ( __ignoreSelection( range ) ) return ;

            range.deleteContents();

            // Range.createContextualFragment() would be useful here but is
            // only relatively recently standardized and is not supported in
            // some browsers (IE9, for one)
            var el = document.createElement("div");
            el.innerHTML = html;
            var frag = document.createDocumentFragment(), node, lastNode;
            while ( (node = el.firstChild) ) {
                lastNode = frag.appendChild(node);
            }
            var firstNode = frag.firstChild;
            range.insertNode(frag);

            // Preserve the selection
            if (lastNode) {
                range = range.cloneRange();
                range.setStartAfter(lastNode);
                if (selectPastedContent) {
                    range.setStartBefore(firstNode);
                } else {
                    range.collapse(true);
                }
                sel.removeAllRanges();
                sel.addRange(range);
            }
        }
    } else if ( (sel = document.selection) && sel.type != "Control") {
        // IE < 9
        var originalRange = sel.createRange();
        originalRange.collapse(true);
        sel.createRange().pasteHTML(html);
        if (selectPastedContent) {
            range = sel.createRange();
            range.setEndPoint("StartToStart", originalRange);
            range.select();
        }
    }
}

function setCursorPosition(el, pos) {
	var isDetatched = $(el).parents('body').length == 0 ;
	if ( isDetatched ) return ;

	pos = pos || 0;

	var range = document.createRange();

	var sel = window.getSelection();

	if (pos == 'end') {
		range.setStartAfter(el);
	} else {
		console.debug('setCursorPosition setting start at pos', el, pos);
		range.setStart(el, pos);
	}

	range.collapse(true);

	sel.removeAllRanges();

	sel.addRange(range);

	if(typeof el[0] != 'undefined') {
		console.debug('setCursorPosition setting focus');
		el.focus();
	}
}

function removeSelectedText() {
    if (window.getSelection || document.getSelection) {
        var oSelection = (window.getSelection ? window : document).getSelection();
        if (oSelection.type == 'Caret' && (oSelection.extentOffset != oSelection.baseOffset)) {
            oSelection.deleteFromDocument();
        } else if (oSelection.type == 'Range') {
            var ss = $(oSelection.baseNode).parent()[0];
            var ssParentTag = $(oSelection.baseNode).closest('.locked.selfClosingTag')[0];
            if ($(ss).hasClass('selected')) {
                $(ss).remove();
            } else if (ssParentTag) {
                $(ssParentTag).remove();
            } else {
                oSelection.deleteFromDocument();
                oSelection.collapseToStart();
            }

        }
    } else {
        document.selection.clear();
    }
}
function stripHTML(dirtyString) {
    var container = document.createElement('div');
    container.innerHTML = dirtyString;
    return container.textContent || container.innerText;
}

function stackTrace() {
    var err = new Error();
    return err.stack;
}

/* FORMATTING FUNCTION  TO TEST */

var LTPLACEHOLDER = "##LESSTHAN##";
var GTPLACEHOLDER = "##GREATERTHAN##";
var re_lt = new RegExp(LTPLACEHOLDER, "g");
var re_gt = new RegExp(GTPLACEHOLDER, "g");
// test jsfiddle http://jsfiddle.net/YgKDu/

function placehold_xliff_tags(segment) {
	segment = segment.replace(/<(g\s*.*?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER);
	segment = segment.replace(/<(\/g)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER);
	segment = segment.replace(/<(x\s*.*?\/)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER);
	segment = segment.replace(/<(bx\s*.*?\/)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER);
	segment = segment.replace(/<(ex\s*.*?\/)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER);
	segment = segment.replace(/<(bpt\s*.*?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER);
	segment = segment.replace(/<(\/bpt)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER);
	segment = segment.replace(/<(ept\s*.*?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER);
	segment = segment.replace(/<(\/ept)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER);
	segment = segment.replace(/<(ph\s*.*?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER);
	segment = segment.replace(/<(\/ph)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER);
	segment = segment.replace(/<(it\s*.*?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER);
	segment = segment.replace(/<(\/ph)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER);
	segment = segment.replace(/<(it\s*.*?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER);
	segment = segment.replace(/<(\/it)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER);
	segment = segment.replace(/<(mrk\s*.*?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER);
	segment = segment.replace(/<(\/mrk)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER);
	return segment;
}

function restore_xliff_tags(segment) {
	segment = segment.replace(re_lt, "<");
	segment = segment.replace(re_gt, ">");
	return segment;
}

function restore_xliff_tags_for_view(segment) {
	segment = segment.replace(re_lt, "&lt;");
	segment = segment.replace(re_gt, "&gt;");
	return segment;
}

function view2rawxliff(segment) {
	// return segment+"____";
	// input : <g id="43">bang & olufsen < 3 </g> <x id="33"/>; --> valore della funzione .text() in cat.js su source, target, source suggestion,target suggestion
	// output : <g id="43"> bang &amp; olufsen are &gt; 555 </g> <x/>

	// caso controverso <g id="4" x="&lt; dfsd &gt;">
	//segment=htmlDecode(segment);
	segment = placehold_xliff_tags(segment);
	segment = htmlEncode(segment);

	segment = restore_xliff_tags(segment);

	return segment;
}

function rawxliff2view(segment) { // currently unused
	// input : <g id="43">bang &amp; &lt; 3 olufsen </g>; <x id="33"/>
	// output : &lt;g id="43"&gt;bang & < 3 olufsen &lt;/g&gt;;  &lt;x id="33"/&gt;
	segment = placehold_xliff_tags(segment);
	segment = htmlDecode(segment);
	segment = segment.replace(/<(.*?)>/i, "&lt;$1&gt;");
	segment = restore_xliff_tags_for_view(segment);		// li rendering avviene via concat o via funzione html()
	return segment;
}

function rawxliff2rawview(segment) { // currently unused
	// input : <g id="43">bang &amp; &lt; 3 olufsen </g>; <x id="33"/>
	segment = placehold_xliff_tags(segment);
	segment = htmlDecode(segment);
	segment = restore_xliff_tags_for_view(segment);
	return segment;
}

function saveSelection() {
	if (UI.savedSel) {
		rangy.removeMarkers(UI.savedSel);
	}

	UI.savedSel = rangy.saveSelection();
	UI.savedSelActiveElement = document.activeElement;
}

function restoreSelection() {
	if (UI.savedSel) {
		rangy.restoreSelection(UI.savedSel, true);
		UI.savedSel = null;
		window.setTimeout(function() {
			if (UI.savedSelActiveElement && typeof UI.savedSelActiveElement.focus != "undefined") {
				UI.savedSelActiveElement.focus();
			}
		}, 1);
	}
}

function selectText(element) {
	var doc = document, text = element, range, selection;
	if (doc.body.createTextRange) {
		range = document.body.createTextRange();
		range.moveToElementText(text);
		range.select();
	} else if (window.getSelection) {
		selection = window.getSelection();
		range = document.createRange();
		range.selectNodeContents(text);
		selection.removeAllRanges();
		selection.addRange(range);
	}
}

function runDownload() {
    var continueDownloadFunction ;

    if( $('#downloadProject').hasClass('disabled') ) return false;

    if ( config.isGDriveProject ) {
        continueDownloadFunction = 'continueDownloadWithGoogleDrive';
    }
    else  {
        continueDownloadFunction = 'continueDownload';
    }

    //the translation mismatches are not a severe Error, but only a warn, so don't display Error Popup
    if ( $("#notifbox").hasClass("warningbox") && UI.globalWarnings.ERROR && UI.globalWarnings.ERROR.total > 0 ) {
        UI.showFixWarningsOnDownload(continueDownloadFunction);
    } else {
        UI[ continueDownloadFunction ]();
    }
}

/**
 * Returns the translation status evaluating the job stats
 */

function translationStatus(stats) {
    var t = 'approved';
    var app = parseFloat(stats.APPROVED);
    var tra = parseFloat(stats.TRANSLATED);
    var dra = parseFloat(stats.DRAFT);
    var rej = parseFloat(stats.REJECTED);

    // If second pass enabled
    if ( config.secondRevisionsCount && stats.reviews ) {

        var revWords1 = stats.reviews.find(function ( value ) {
            return value.revision_number === 1;
        });

        var revWords2 = stats.reviews.find(function ( value ) {
            return value.revision_number === 2;
        });

        if ( revWords1 && _.round(parseFloat(revWords1.advancement_wc)) > 0 ) {
            app = parseFloat(revWords1.advancement_wc);
        } else if ( revWords2 && _.round(parseFloat(revWords2.advancement_wc)) > 0 ) {
            app = parseFloat(revWords2.advancement_wc);
            t = 'approved-2ndpass';
        }
    }


    if (tra) t = 'translated';
    if (dra) t = 'draft';
    if (rej) t = 'draft';

    if( !tra && !dra && !rej && !app ){
        t = 'draft';
    }

    return t ;
}

function getSelectionHtml() {
	var html = "";
	if (typeof window.getSelection != "undefined") {
		var sel = window.getSelection();
		if (sel.rangeCount) {
			var container = document.createElement("div");
			for (var i = 0, len = sel.rangeCount; i < len; ++i) {
                container.appendChild(sel.getRangeAt(i).cloneContents());
			}
			html = container.innerHTML;
		}
	} else if (typeof document.selection != "undefined") {
		if (document.selection.type == "Text") {
			html = document.selection.createRange().htmlText;
		}
	}
	return html;
}

function insertHtmlAfterSelection(html) {
    var sel, range;
    if (window.getSelection) {
        sel = window.getSelection();
        if (sel.getRangeAt && sel.rangeCount) {
            range = window.getSelection().getRangeAt(0);
            // range.collapse(false);

            // Range.createContextualFragment() would be useful here but is
            // non-standard and not supported in all browsers (IE9, for one)
            var el = document.createElement("div");
            el.innerHTML = html;
            var frag = document.createDocumentFragment(), node, lastNode;
            while ( (node = el.firstChild) ) {
                lastNode = frag.appendChild(node);
            }
            range.insertNode(frag);
        }
    } else if (document.selection && document.selection.createRange) {
        range = document.selection.createRange();
        range.collapse(false);
        range.pasteHTML(html);
    }
    return range;
}

function ParsedHash( hash ) {
    var split ;
    var actionSep = ',' ;
    var chunkSep = '-';
    var that = this ;
    var _obj = {};

    var processObject = function( obj ) {
        _obj = obj ;
    };

    var processString = function( hash ) {
        if ( hash.indexOf('#') == 0 ) hash = hash.substr(1);

        if ( hash.indexOf( actionSep ) != -1 ) {
            split = hash.split( actionSep );

            _obj.segmentId = split[0];
            _obj.action = split[1];
        } else {
            _obj.segmentId = hash ;
            _obj.action = null;
        }

        if ( _obj.segmentId.indexOf( chunkSep ) != -1 ) {
            split = hash.split( chunkSep );

            _obj.splittedSegmentId = split[0];
            _obj.chunkId = split[1];
        }
    }

    if (typeof hash === 'string') {
        processString( hash );
    } else {
        processObject( hash );
    }

    this.segmentId = _obj.segmentId ;
    this.action = _obj.action ;
    this.splittedSegmentId = _obj.splittedSegmentId ;
    this.chunkId = _obj.chunkId ;

    this.isComment = function() {
        return _obj.action == MBC.const.commentAction ;
    }

    this.toString = function() {
        var hash = '';
        if ( _obj.splittedSegmentId ) {
            hash = _obj.splittedSegmentId + chunkSep + _obj.chunkId ;
        } else {
            hash = _obj.segmentId ;
        }
        if ( _obj.action ) {
            hash = hash + actionSep + _obj.action ;
        }
        return hash ;
    }

    this.onlyActionRemoved = function( hash ) {
        var current = new ParsedHash( hash );
        var diff = this.toString().split( current.toString() );
        return MBC.enabled() && (diff[1] == actionSep + MBC.const.commentAction) ;
    }

    this.hashCleanupRequired = function() {
        return MBC.enabled() && this.isComment();
    }

    this.cleanupHash = function() {
        notifyModules();
        window.location.hash = UI.parsedHash.segmentId ;
    }

    var notifyModules = function() {
        MBC.enabled() && that.isComment() && MBC.setLastCommentHash( that );
    }
}

function setBrowserHistoryBehavior() {

    window.onpopstate = function() {
        segmentId = location.hash.substr(1); // TODO: check this global var is no longer used and remove it

        if ( UI.parsedHash.onlyActionRemoved( window.location.hash ) ) {
            return ;
        }

        UI.parsedHash = new ParsedHash( window.location.hash );

        if ( UI.parsedHash.hashCleanupRequired() ) {
            UI.parsedHash.cleanupHash();
        }

        function updateAppByPopState() {
            var segment = UI.getSegmentById( UI.parsedHash.segmentId );
            if ( UI.currentSegmentId === UI.parsedHash.segmentId ) return;
            if ( segment.length ) {
                UI.gotoSegment( UI.parsedHash.segmentId );
            } else {
                if ($('section').length) {
                    UI.pointBackToSegment( UI.parsedHash.segmentId );
                }
            }
        }
        updateAppByPopState();

    };

    UI.parsedHash = new ParsedHash( window.location.hash );
    UI.parsedHash.hashCleanupRequired() && UI.parsedHash.cleanupHash();
}


function goodbye(e) {

    UI.clearStorage('contribution');

    if ( $( '#downloadProject' ).hasClass( 'disabled' ) || $( 'tr td a.downloading' ).length || $( '.popup-tm td.uploadfile.uploading' ).length ) {
        return say_goodbye( 'You have a pending operation. Are you sure you want to quit?' );
    }

    if ( UI.offline ) {
        if(UI.setTranslationTail.length) {
            return say_goodbye( 'You are working in offline mode. If you proceed to refresh you will lose all the pending translations. Do you want to proceed with the refresh ?' );
        }
    }


    //set dont_confirm_leave to 1 when you want the user to be able to leave without confirmation
    function say_goodbye( leave_message ){

        if ( typeof leave_message !== 'undefined' ) {
            if ( !e ) e = window.event;
            //e.cancelBubble is supported by IE - this will kill the bubbling process.
            e.cancelBubble = true;
            e.returnValue = leave_message;
            //e.stopPropagation works in Firefox.
            if ( e.stopPropagation ) {
                e.stopPropagation();
                e.preventDefault();
            }
            //return works for Chrome and Safari
            return leave_message;
        }

    }

}

function cleanupHTMLCharsForDiff( string ) {
	return replacePlaceholder(string.replace(/&nbsp;/g, ''));
}

function replacePlaceholder(string) {
   return  string
       .replace( config.lfPlaceholderRegex, "softReturnMonad")
        .replace( config.crPlaceholderRegex, "crPlaceholder" )
        .replace( config.crlfPlaceholderRegex, "brMarker" )
        .replace( config.tabPlaceholderRegex, "tabMarkerMonad" )
        .replace( config.nbspPlaceholderRegex, "nbspPlMark" )
}

function restorePlaceholders(string) {
    return string
        .replace(/softReturnMonad/g , config.lfPlaceholder)
        .replace(/crPlaceholder/g,  config.crPlaceholder)
        .replace(/brMarker/g,  config.crlfPlaceholder )
        .replace(/tabMarkerMonad/g, config.tabPlaceholder)
        .replace(/nbspPlMark/g, config.nbspPlaceholder)
}

function trackChangesHTML(source, target) {
    /*
    There are problems when you delete or add a tag next to another, the algorithm that makes the diff fails to recognize the tags,
    they come out of the function broken.
    Before passing them to the function that makes the diff we replace all the tags with placeholders and we keep a map of the tags
    indexed with the id of the tags.
     */
    var phTagsObject = {};
    var diff;
    source = source.replace( /&lt;(g|x|bx|ex|bpt|ept|ph|it|mrk).*?id="(.*?)".*?\/&gt;/gi, function (match, group1, group2) {
        if ( _.isUndefined(phTagsObject[group2]) ) {
            phTagsObject[group2] = match;
        }
        return '<' + Base64.encode(group2) +'> ';
    });

    target = target.replace( /&lt;(g|x|bx|ex|bpt|ept|ph|it|mrk).*?id="(.*?)".*?\/&gt;/gi, function (match, gruop1, group2) {
        if ( _.isUndefined(phTagsObject[group2]) ) {
            phTagsObject[group2] = match;
        }
        return '<'+ Base64.encode(group2) +'> ';
    });

    diff   = UI.dmp.diff_main(
		cleanupHTMLCharsForDiff( source ),
		cleanupHTMLCharsForDiff( target )
	);

    UI.dmp.diff_cleanupSemantic( diff ) ;

    /*
    Before adding spans to identify added or subtracted portions we need to check and fix broken tags
     */
    diff = setUnclosedTagsInDiff(diff);
    var diffTxt = '';

    $.each(diff, function (index, text) {
        text[1] = text[1].replace( /<(.*?)>/gi, function (match, text) {
            try {
                var decodedText = Base64.decode( text );
                if ( !_.isUndefined( phTagsObject[ decodedText ] ) ) {
                    return phTagsObject[ decodedText ];
                }
                return match;
            } catch ( e ) {
                return match;
            }

        });
        var rootElem;
        var newElem;
        if(this[0] === -1) {
            rootElem = $( document.createElement( 'div' ) );
            newElem = $.parseHTML( '<span class="deleted"/>' );
            $( newElem ).text( htmlDecode(text[1]) );
            rootElem.append( newElem );
            diffTxt += $( rootElem ).html();
        } else if(text[0] === 1) {
            rootElem = $( document.createElement( 'div' ) );
            newElem = $.parseHTML( '<span class="added"/>' );
            $( newElem ).text( htmlDecode(text[1]) );
            rootElem.append( newElem );
            diffTxt += $( rootElem ).html();
        } else {
            diffTxt += text[1];
        }
    });

    return restorePlaceholders(diffTxt) ;
}
/**
 *This function takes in the array that exits the UI.dmp.diff_main function and parses the array elements to see if they contain broken tags.
 * The array is of the type:
 *
 * [0, "text"],
 * [-1, "deletedText"]
 * [1, "addedText"]
 *
 * For each element of the array in the first position there is 0, 1, -1 which indicate if the text is equal, added, removed
 */
function setUnclosedTagsInDiff(array) {

    /*
    Function to understand if an element contains broken tags
     */
    var thereAreIncompletedTagsInDiff = function ( text ) {
        return (text.indexOf('<') > -1 || text.indexOf('>') > -1) &&
            ( (text.split("<").length - 1) !== (text.split(">").length - 1) ||  text.indexOf('<') >= text.indexOf('>'))
    };
    /*
    Function to understand if an element contains broken tags where the opening part is missing
     */
    var thereAreCloseTags = function ( text ) {
        return thereAreIncompletedTagsInDiff(text) && ( ( (item[1].split("<").length - 1) < (item[1].split(">").length - 1) ) ||
            ( item[1].indexOf('>') > -1 && item[1].indexOf('>') < item[1].indexOf('<')))
    };
    /*
    Function to understand if an element contains broken tags where the closing part is missing
     */
    var thereAreOpenTags = function ( text ) {
        return thereAreIncompletedTagsInDiff(text) && ( ( (item[1].split("<").length - 1) < (item[1].split(">").length - 1) ) ||
            ( item[1].indexOf('<') > -1 && item[1].indexOf('>') > item[1].indexOf('<')))
    };
    var i;
    var indexTemp;
    var adding = false;
    var tagToMoveOpen = "";
    var tagToMoveClose = "";
    for (i = 0; i < array.length; i++) {
        var item = array[i];
        var thereAreUnclosedTags =  thereAreIncompletedTagsInDiff(item[1]);
        if ( !adding && item[0] === 0) {
            if (thereAreUnclosedTags) {
                tagToMoveOpen = item[1].substr(item[1].lastIndexOf('<'), item[1].length + 1);
                array[i][1] = item[1].substr(0, item[1].lastIndexOf('<'));
                indexTemp = i;
                adding = true;
            }
        } else if (adding && item[0] === 0){
            if ( thereAreUnclosedTags && thereAreCloseTags(item[1]) ) {
                tagToMoveClose = item[1].substr( 0, item[1].indexOf( '>' ) + 1 );
                tagToMoveOpen = "";
                array[i][1] = item[1].substr( item[1].indexOf( '>' ) + 1, item[1].length + 1 );
                i = indexTemp;
            } else{
                if ( thereAreUnclosedTags && thereAreOpenTags(item[1]) ) {
                    i = i-1; //There are more unclosed tags, restart from here
                }
                indexTemp = 0;
                adding = false;
                tagToMoveOpen = "";
                tagToMoveClose = "";

            }
        } else if (adding) {
            array[i][1] = tagToMoveOpen + item[1] + tagToMoveClose;
        }
    }
    return array;
}



function getDiffPatch(source, target) {
    var diff   = UI.dmp.diff_main(
        cleanupHTMLCharsForDiff( source ),
        cleanupHTMLCharsForDiff( target )
    );

    UI.dmp.diff_cleanupSemantic( diff ) ;
    return diff;
}

function trackChangesHTMLFromDiffArray(diff) {
    var diffTxt = '';

    $.each(diff, function (index) {
        if(this[0] == -1) {
            var rootElem = $( document.createElement( 'div' ) );
            var newElem = $.parseHTML( '<span class="deleted"/>' );
            $( newElem ).text( htmlDecode(this[1]) );
            rootElem.append( newElem );
            diffTxt += $( rootElem ).html();
        } else if(this[0] == 1) {
            var rootElem = $( document.createElement( 'div' ) );
            var newElem = $.parseHTML( '<span class="added"/>' );
            $( newElem ).text( htmlDecode(this[1]) );
            rootElem.append( newElem );
            diffTxt += $( rootElem ).html();
        } else {
            diffTxt += this[1];
        }
    });
    return restorePlaceholders(diffTxt);
}



$.fn.isOnScreen = function() {

	var win = $(window);

	var viewport = {
		top: win.scrollTop(),
		left: win.scrollLeft()
	};
	console.log('viewport: ', viewport);

	viewport.right = viewport.left + win.width();
	viewport.bottom = viewport.top + win.height();

	var bounds = this.offset();
	bounds.right = bounds.left + this.outerWidth();
	bounds.bottom = bounds.top + this.outerHeight();


	return (!(viewport.right < bounds.left || viewport.left > bounds.right || viewport.bottom < bounds.top || viewport.top > bounds.bottom));

};

$.fn.countdown = function (callback, duration, message) {
    // If no message is provided, we use an empty string
    message = message || "";
    // Get reference to container, and set initial content
    var container = $(this[0]).html(duration + message);
    // Get reference to the interval doing the countdown
    var countdown = setInterval(function () {
        // If seconds remain
        if (--duration) {
            // Update our container's message
            container.html(duration + message);
        // Otherwise
        } else {
            // Clear the countdown interval
            clearInterval(countdown);
            console.log('container: ', container);
            // And fire the callback passing our container as `this`
            callback.call(container);
        }
    // Run interval every 1000ms (1 second)
    }, 1000);

    return countdown;

};

Object.size = function(obj) {
    var size = 0, key;
    for (key in obj) {
        if (obj.hasOwnProperty(key)) size++;
    }
    return size;
};

String.prototype.splice = function( idx, rem, s ) {
    return (this.slice(0,idx) + s + this.slice(idx + Math.abs(rem)));
};

function lev(s1, s2) {
  //       discuss at: http://phpjs.org/functions/levenshtein/
  //      original by: Carlos R. L. Rodrigues (http://www.jsfromhell.com)
  //      bugfixed by: Onno Marsman
  //       revised by: Andrea Giammarchi (http://webreflection.blogspot.com)
  // reimplemented by: Brett Zamir (http://brett-zamir.me)
  // reimplemented by: Alexander M Beedie
  //        example 1: levenshtein('Kevin van Zonneveld', 'Kevin van Sommeveld');
  //        returns 1: 3

  if (s1 == s2) {
    return 0;
  }

  var s1_len = s1.length;
  var s2_len = s2.length;
  if (s1_len === 0) {
    return s2_len;
  }
  if (s2_len === 0) {
    return s1_len;
  }

  // BEGIN STATIC
  var split = false;
  try {
    split = !('0')[0];
  } catch (e) {
    split = true; // Earlier IE may not support access by string index
  }
  // END STATIC
  if (split) {
    s1 = s1.split('');
    s2 = s2.split('');
  }

  var v0 = new Array(s1_len + 1);
  var v1 = new Array(s1_len + 1);

  var s1_idx = 0,
    s2_idx = 0,
    cost = 0;
  for (s1_idx = 0; s1_idx < s1_len + 1; s1_idx++) {
    v0[s1_idx] = s1_idx;
  }
  var char_s1 = '',
    char_s2 = '';
  for (s2_idx = 1; s2_idx <= s2_len; s2_idx++) {
    v1[0] = s2_idx;
    char_s2 = s2[s2_idx - 1];

    for (s1_idx = 0; s1_idx < s1_len; s1_idx++) {
      char_s1 = s1[s1_idx];
      cost = (char_s1 == char_s2) ? 0 : 1;
      var m_min = v0[s1_idx + 1] + 1;
      var b = v1[s1_idx] + 1;
      var c = v0[s1_idx] + cost;
      if (b < m_min) {
        m_min = b;
      }
      if (c < m_min) {
        m_min = c;
      }
      v1[s1_idx + 1] = m_min;
    }
    var v_tmp = v0;
    v0 = v1;
    v1 = v_tmp;
  }
  return v0[s1_len];
}
function replaceSelectedText(replacementText) {
    var sel, range;
    if (window.getSelection) {
        sel = window.getSelection();
        if (sel.rangeCount) {
            range = sel.getRangeAt(0);
            range.deleteContents();
            range.insertNode(document.createTextNode(replacementText));
        }
    } else if (document.selection && document.selection.createRange) {console.log('c');
        range = document.selection.createRange();
        range.text = replacementText;
    }
}
function replaceSelectedHtml(replacementHtml, range) {
    var sel;
    if (range) {
        range.deleteContents();
        pasteHtmlAtCaret(replacementHtml);
    } else if (window.getSelection) {
        sel = window.getSelection();
        if (sel.rangeCount) {
            range = sel.getRangeAt(0);
            range.deleteContents();
            pasteHtmlAtCaret(replacementHtml);
//            range.pasteHtml(replacementHtml);
        }
    } else if (document.selection && document.selection.createRange) {
        range = document.selection.createRange();
        range.text = replacementText;
    }
}
function capitaliseFirstLetter(string)
{
    return string.charAt(0).toUpperCase() + string.slice(1);
}
function toTitleCase(str)
{
    return str.replace(/[\wwÀ-ÿЀ-џ]\S*/g, function(txt){
    	return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
    });
}

function getRangeObject(selectionObject) {
//    console.log('getRangeObject');
    if (!UI.isSafari) {
//    if (selectionObject.getRangeAt) {
        return selectionObject.getRangeAt(0);
    }
    else { // Safari!
        var range = document.createRange();
        range.setStart(selectionObject.anchorNode,selectionObject.anchorOffset);
        range.setEnd(selectionObject.focusNode,selectionObject.focusOffset);
        return range;
    }
}


if (typeof String.prototype.startsWith != 'function') {
    String.prototype.startsWith = function (str){
        return this.indexOf(str) == 0;
    };
}

if (typeof String.prototype.endsWith !== 'function') {
    String.prototype.endsWith = function(suffix) {
        return this.indexOf(suffix, this.length - suffix.length) !== -1;
    };
}

function isTranslated(section) {
    return ! (
        section.hasClass('status-new') ||
        section.hasClass('status-draft')
    );
}

function eventFromReact(e) {
    return e.target.hasAttribute('data-reactid');
}

function hackIntercomButton(on ) {
    var button = $( document ).find( '.support-tip-button' );
    if ( on ) {
        button.data( 'mbc-zindex', button.css( 'z-index' ) );
        button.css( 'z-index', -1 );
    } else {
        button.css( 'z-index', button.data( 'mbc-zindex' ) );
    }
}

function cleanupSplitMarker( string ) {
	return string.split( UI.splittedTranslationPlaceholder ).join();
}

function absoluteId( id ) {
	return id.split('-')[0]; 
}

/**
 * Returns a clickable link with mailto support.
 */
function linkedSupportEmail() {
	return sprintf('<a href="mailto:%s">%s</a>', config.support_mail, config.support_mail );
}

/**
 * A generic error message to show in modal window.
 *
 * @returns {*}
 */
function genericErrorAlertMessage() {
	return APP.alert({
		msg: sprintf('There was an error while saving data to server, please try again. ' +
			'If the problem persists please contact %s reporting the web address of the current browser tab.',
			linkedSupportEmail() )
	});
}

function getSelectionData(selection, container) {
    var data = {};
    data.start_node = $.inArray( selection.anchorNode, container.contents() );
    if (data.start_node<0) {
        //this means that the selection is probably ending inside a lexiqa tag,
        //or matecat tag/marking
        data.start_node = $.inArray( $(selection.anchorNode).parent()[0], container.contents() );
    }
    var nodes = container.contents();//array of nodes
    if (data.start_node ===0) {
        data.start_offset = selection.anchorOffset;
    } else {
        data.start_offset = 0;
        for (var i=0;i<data.start_node;i++) {
            data.start_offset += nodes[i].textContent.length;
        }
        data.start_offset += selection.anchorOffset;
        data.start_node = 0;
    }

    data.end_node = $.inArray( selection.focusNode, container.contents() );
    if (data.end_node<0) {
        //this means that the selection is probably ending inside a lexiqa tag,
        //or matecat tag/marking
        data.end_node = $.inArray( $(selection.focusNode).parent()[0], container.contents() );
    }
    if (data.end_node ===0)
        data.end_offset =  selection.focusOffset;
    else {
        data.end_offset = 0;
        for (var i=0;i<data.end_node;i++) {
            data.end_offset += nodes[i].textContent.length;
        }
        data.end_offset += selection.focusOffset;
        data.end_node = 0;
    }
    data.selected_string = selection.toString() ;
    return data ;
}